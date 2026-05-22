<script setup lang="ts">
import { onMounted, onUnmounted, computed, reactive, watch, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProperty } from '@/composables/useProperty'
import { usePropertyStore } from '@/stores/property'
import { propertyService } from '@/services/propertyService'
import PropertyGallery from '@/components/property/PropertyGallery.vue'
import type { PropertyType, PropertyStatus, StorePropertyPayload } from '@/types/property'
import type { Map as LeafletMap, Marker as LeafletMarker } from 'leaflet'

const route = useRoute()
const router = useRouter()
const { selectedProperty, isLoading, error, fetchProperty, uploadPhoto, deletePhoto } =
  useProperty()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => (isEditMode.value ? 'Edit Property' : 'Add Property'))

// ─── Form State ────────────────────────────────────────────────────────────────

const form = reactive<StorePropertyPayload>({
  name: '',
  address: '',
  type: 'kos',
  status: 'available',
  latitude: -6.1751,
  longitude: 106.8272,
  description: '',
  monthly_price: 0,
})

const errors = reactive<Partial<Record<keyof StorePropertyPayload, string>>>({})

// ─── Map Picker & Autocomplete Geocoding Search ───────────────────────────────

const mapContainer = ref<HTMLElement | null>(null)
let leafletMap: LeafletMap | null = null
let marker: LeafletMarker | null = null

const isReverseGeocoding = ref(false)
const isSelectingSuggestion = ref(false)

const suggestions = ref<any[]>([])
const showSuggestions = ref(false)
let debounceTimeout: ReturnType<typeof setTimeout> | null = null

async function initMap(lat: number = -6.1751, lng: number = 106.8272) {
  if (!mapContainer.value) return
  
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')
  
  // Fix marker icons
  delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
  })

  leafletMap = L.map(mapContainer.value).setView([lat, lng], 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(leafletMap)

  marker = L.marker([lat, lng], { draggable: true }).addTo(leafletMap)

  // Track coordinates and reverse geocode
  marker.on('dragend', async () => {
    const position = marker!.getLatLng()
    form.latitude = position.lat
    form.longitude = position.lng
    await reverseGeocode(position.lat, position.lng)
  })

  leafletMap.on('click', async (e) => {
    const { lat, lng } = e.latlng
    form.latitude = lat
    form.longitude = lng
    marker!.setLatLng([lat, lng])
    await reverseGeocode(lat, lng)
  })
}

function destroyMap() {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    marker = null
  }
}

function clickOutsideHandler(e: MouseEvent) {
  const el = document.getElementById('address')
  const dropdown = document.querySelector('.address-suggestions')
  if (el && !el.contains(e.target as Node) && dropdown && !dropdown.contains(e.target as Node)) {
    showSuggestions.value = false
  }
}

function handleAddressInput() {
  if (isSelectingSuggestion.value) {
    isSelectingSuggestion.value = false
    return
  }
  
  if (debounceTimeout) clearTimeout(debounceTimeout)
  
  const query = form.address.trim()
  if (query.length < 3) {
    suggestions.value = []
    showSuggestions.value = false
    return
  }

  debounceTimeout = setTimeout(async () => {
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`)
      const data = await res.json()
      suggestions.value = data || []
      showSuggestions.value = suggestions.value.length > 0
    } catch (err) {
      console.error('Error fetching autocomplete suggestions:', err)
    }
  }, 400)
}

function handleAddressFocus() {
  if (suggestions.value.length > 0) {
    showSuggestions.value = true
  }
}

function getPlacePrimaryName(item: any): string {
  if (!item.display_name) return ''
  const parts = item.display_name.split(',')
  return parts[0].trim()
}

function selectSuggestion(item: any) {
  isSelectingSuggestion.value = true
  form.address = item.display_name
  form.latitude = parseFloat(item.lat)
  form.longitude = parseFloat(item.lon)
  
  if (leafletMap && marker) {
    leafletMap.setView([form.latitude, form.longitude], 16)
    marker.setLatLng([form.latitude, form.longitude])
  }
  
  suggestions.value = []
  showSuggestions.value = false
}

async function reverseGeocode(lat: number, lng: number) {
  isReverseGeocoding.value = true
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`)
    const data = await res.json()
    if (data && data.display_name) {
      isSelectingSuggestion.value = true
      form.address = data.display_name
    }
  } catch (err) {
    console.error('Error reverse geocoding:', err)
  } finally {
    isReverseGeocoding.value = false
  }
}

// ─── Price Comma Formatting ───────────────────────────────────────────────────

const formattedMonthlyPrice = computed({
  get() {
    if (!form.monthly_price) return ''
    return new Intl.NumberFormat('en-US').format(form.monthly_price)
  },
  set(val: string) {
    const cleanVal = val.replace(/\D/g, '')
    form.monthly_price = cleanVal ? parseInt(cleanVal, 10) : 0
  }
})

// ─── Property Photos Section State ─────────────────────────────────────────────

const localPhotos = ref<File[]>([])
const localPhotoPreviews = ref<string[]>([])

function handleLocalPhotoChange(event: Event) {
  const target = event.target as HTMLInputElement
  const files = target.files
  if (!files) return

  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    localPhotos.value.push(file)
    localPhotoPreviews.value.push(URL.createObjectURL(file))
  }
  
  target.value = ''
}

function removeLocalPhoto(index: number) {
  localPhotos.value.splice(index, 1)
  localPhotoPreviews.value.splice(index, 1)
}

async function handleUploadPhoto(file: File) {
  if (selectedProperty.value) {
    await uploadPhoto(selectedProperty.value.id, file)
  }
}

async function handleDeletePhoto(mediaId: number) {
  if (selectedProperty.value) {
    await deletePhoto(selectedProperty.value.id, mediaId)
  }
}

// ─── Populate form on edit mode ────────────────────────────────────────────────

onMounted(async () => {
  document.addEventListener('click', clickOutsideHandler)
  if (isEditMode.value) {
    await fetchProperty(route.params.id as string)
  } else {
    // Default to Jakarta center on create
    form.latitude = -6.1751
    form.longitude = 106.8272
    setTimeout(() => {
      initMap(-6.1751, 106.8272)
    }, 50)
  }
})

watch(selectedProperty, (property) => {
  if (property && isEditMode.value) {
    form.name = property.name
    form.address = property.address
    form.type = property.type
    form.status = property.status
    form.latitude = property.latitude
    form.longitude = property.longitude
    form.description = property.description ?? ''
    form.monthly_price = property.monthly_price

    // Reinitialize map center on correct coordinates
    destroyMap()
    setTimeout(() => {
      initMap(property.latitude, property.longitude)
    }, 50)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', clickOutsideHandler)
  destroyMap()
})

// ─── Validation ────────────────────────────────────────────────────────────────

function validate(): boolean {
  // Clear previous errors
  Object.keys(errors).forEach((k) => delete errors[k as keyof StorePropertyPayload])

  let valid = true

  if (!form.name.trim()) { errors.name = 'Name is required.'; valid = false }
  if (!form.address.trim()) { errors.address = 'Address is required.'; valid = false }
  if (!form.type) { errors.type = 'Type is required.'; valid = false }
  if (!form.status) { errors.status = 'Status is required.'; valid = false }
  if (form.latitude === undefined || form.latitude === null || form.latitude < -90 || form.latitude > 90) { errors.latitude = 'Please pin a valid location coordinates on the map.'; valid = false }
  if (form.longitude === undefined || form.longitude === null || form.longitude < -180 || form.longitude > 180) { errors.longitude = 'Please pin a valid location coordinates on the map.'; valid = false }
  if (form.monthly_price <= 0) { errors.monthly_price = 'Monthly price is required and must be greater than 0.'; valid = false }

  return valid
}

// ─── Submit ────────────────────────────────────────────────────────────────────

const isSubmittingForm = ref(false)
const errorMsg = ref<string | null>(null)

async function handleSubmit() {
  if (!validate()) return

  isSubmittingForm.value = true
  errorMsg.value = null
  try {
    const payload: StorePropertyPayload = {
      name: form.name.trim(),
      address: form.address.trim(),
      type: form.type,
      status: form.status,
      latitude: Number(form.latitude),
      longitude: Number(form.longitude),
      description: form.description?.trim() || undefined,
      monthly_price: Number(form.monthly_price),
    }

    let propertyId = ''
    if (isEditMode.value) {
      propertyId = route.params.id as string
      await propertyService.update(propertyId, payload)
    } else {
      const res = await propertyService.create(payload)
      propertyId = res.data.id
      // Add to Pinia store
      usePropertyStore().addProperty(res.data)
    }

    // Sequentially upload selected local photos
    if (localPhotos.value.length > 0) {
      for (const file of localPhotos.value) {
        await propertyService.uploadPhoto(propertyId, file)
      }
    }

    // Redirect to property detail
    router.push({ name: 'property-detail', params: { id: propertyId } })
  } catch (err: any) {
    console.error(err)
    errorMsg.value = err.response?.data?.message || 'Failed to save property. Please check your inputs.'
  } finally {
    isSubmittingForm.value = false
  }
}

const typeOptions: { label: string; value: PropertyType }[] = [
  { label: 'Kos', value: 'kos' },
  { label: 'Apartment', value: 'apartment' },
  { label: 'Ruko', value: 'ruko' },
]

const statusOptions: { label: string; value: PropertyStatus }[] = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' },
]
</script>

<template>
  <div class="page-content" style="max-width: 760px;">
    <!-- Back Link -->
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="m15 18-6-6 6-6"/>
      </svg>
      Back
    </button>

    <div class="page-header" style="margin-bottom: 20px;">
      <h1 class="page-title">{{ pageTitle }}</h1>
    </div>

    <!-- Server-side or client-side error -->
    <div v-if="errorMsg" class="alert alert--error">{{ errorMsg }}</div>
    <div v-else-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="shimmer" style="height: 380px; border-radius: 16px;" />

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <!-- General Details Card -->
      <div class="card" style="margin-bottom: 24px;">
        <p class="section-label">General details</p>

        <!-- Name -->
        <div class="field">
          <label class="form-label" for="name">Property Name <span style="color: #dc2626;">*</span></label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="form-input"
            :class="{ 'input-error': errors.name }"
            placeholder="e.g. Kos Harmoni"
          />
          <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
        </div>

        <!-- Address & Location Map Picker (Combined Autocomplete) -->
        <div class="field" style="margin-bottom: 24px; position: relative;">
          <label class="form-label" for="address">Address <span style="color: #dc2626;">*</span></label>
          <textarea
            id="address"
            v-model="form.address"
            class="form-textarea"
            :class="{ 'input-error': errors.address }"
            placeholder="e.g. Jl. Harmoni No. 12, Jakarta Pusat"
            rows="2"
            @input="handleAddressInput"
            @focus="handleAddressFocus"
          />
          <span v-if="errors.address" class="form-error">{{ errors.address }}</span>

          <!-- Autocomplete Suggestions Dropdown -->
          <div v-if="showSuggestions && suggestions.length > 0" class="address-suggestions">
            <div
              v-for="item in suggestions"
              :key="item.place_id"
              class="suggestion-item"
              @click="selectSuggestion(item)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="suggestion-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
              </svg>
              <div class="suggestion-text">
                <span class="suggestion-name">{{ getPlacePrimaryName(item) }}</span>
                <span class="suggestion-full">{{ item.display_name }}</span>
              </div>
            </div>
          </div>

          <div ref="mapContainer" class="form-map" style="margin-top: 12px;" />
          
          <div class="coordinates-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            <span v-if="isReverseGeocoding">Resolving address from map pin...</span>
            <span v-else>Pinned Location: {{ form.latitude.toFixed(6) }}, {{ form.longitude.toFixed(6) }}</span>
          </div>
          <span v-if="errors.latitude" class="form-error">{{ errors.latitude }}</span>
        </div>

        <!-- Type & Status row -->
        <div class="field-row">
          <div class="field">
            <label class="form-label" for="type">Type <span style="color: #dc2626;">*</span></label>
            <select
              id="type"
              v-model="form.type"
              class="form-select"
              :class="{ 'input-error': errors.type }"
            >
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <span v-if="errors.type" class="form-error">{{ errors.type }}</span>
          </div>

          <div class="field">
            <label class="form-label" for="status">Status <span style="color: #dc2626;">*</span></label>
            <select
              id="status"
              v-model="form.status"
              class="form-select"
              :class="{ 'input-error': errors.status }"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <span v-if="errors.status" class="form-error">{{ errors.status }}</span>
          </div>
        </div>

        <!-- Monthly Price (Commas separator) -->
        <div class="field">
          <label class="form-label" for="monthly_price">Monthly Price (IDR) <span style="color: #dc2626;">*</span></label>
          <div class="prefix-group">
            <span class="prefix-text">Rp</span>
            <input
              id="monthly_price"
              v-model="formattedMonthlyPrice"
              type="text"
              class="form-input form-input--prefixed"
              :class="{ 'input-error': errors.monthly_price }"
              placeholder="1,500,000"
            />
          </div>
          <span v-if="errors.monthly_price" class="form-error">{{ errors.monthly_price }}</span>
        </div>

        <!-- Description -->
        <div class="field" style="margin-bottom: 0;">
          <label class="form-label" for="description">Description <span style="font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--g400);">(optional)</span></label>
          <textarea
            id="description"
            v-model="form.description"
            class="form-textarea"
            placeholder="Describe the property, facilities, and location highlights…"
            rows="4"
          />
        </div>
      </div>

      <!-- Property Photos Section -->
      <div class="card" style="margin-bottom: 24px;">
        <p class="section-label">Property Photos</p>
        
        <!-- Create Mode Photos Management -->
        <div v-if="!isEditMode">
          <div class="photo-upload-zone">
            <input
              id="photos-input"
              type="file"
              multiple
              accept="image/*"
              style="display: none;"
              @change="handleLocalPhotoChange"
            />
            <label for="photos-input" class="photo-upload-label">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
              </svg>
              <span>Click to select property photos</span>
              <span class="photo-upload-subtext">You can select multiple photos at once</span>
            </label>
          </div>

          <!-- Previews Grid -->
          <div v-if="localPhotoPreviews.length > 0" class="photo-previews-grid">
            <div v-for="(preview, idx) in localPhotoPreviews" :key="idx" class="photo-preview-item">
              <img :src="preview" class="photo-preview-image" />
              <button type="button" class="photo-preview-delete" @click="removeLocalPhoto(idx)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Edit Mode Live Photos Gallery -->
        <div v-else>
          <PropertyGallery
            v-if="selectedProperty"
            :photos="selectedProperty.photos"
            :property-id="selectedProperty.id"
            :can-manage="true"
            :is-uploading="isLoading"
            @upload="handleUploadPhoto"
            @delete="handleDeletePhoto"
          />
        </div>
      </div>

      <!-- Actions -->
      <div style="display: flex; gap: 16px; align-items: center; margin-top: 8px;">
        <button type="submit" class="btn-primary" :disabled="isSubmittingForm">
          <span v-if="isSubmittingForm" class="spinner" style="margin-right: 6px;" />
          {{ isEditMode ? 'Save Changes' : 'Create Property' }}
        </button>
        <button type="button" class="btn-cancel" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 20px;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 520px) {
  .field-row {
    grid-template-columns: 1fr;
  }
}

.input-error {
  border-color: #dc2626 !important;
}

/* Prefix group for monthly price */
.prefix-group {
  position: relative;
  display: flex;
  align-items: center;
}
.prefix-text {
  position: absolute;
  left: 14px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--g400);
  pointer-events: none;
}
.form-input--prefixed {
  padding-left: 36px;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Form Map Picker styling */
.form-map {
  width: 100%;
  height: 240px;
  border-radius: 12px;
  border: 1px solid var(--g200);
  margin-top: 10px;
  z-index: 1;
}

/* Autocomplete suggestions */
.address-suggestions {
  position: absolute;
  top: 92px;
  left: 0;
  width: 100%;
  max-height: 240px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid var(--g200);
  border-radius: 12px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  z-index: 100;
  margin-top: 4px;
}

.suggestion-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  transition: background-color 0.15s ease;
  border-bottom: 1px solid var(--g50);
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item:hover {
  background-color: var(--g50);
}

.suggestion-icon {
  width: 16px;
  height: 16px;
  color: var(--g400);
  margin-top: 3px;
  flex-shrink: 0;
}

.suggestion-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.suggestion-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--g900);
}

.suggestion-full {
  font-size: 0.72rem;
  color: var(--g500);
  line-height: 1.4;
}

.coordinates-info {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: 0.75rem;
  color: var(--g500);
  font-weight: 500;
}

.coordinates-info svg {
  width: 14px;
  height: 14px;
  color: var(--g400);
}

/* Address textarea compactness override */
.form-textarea#address {
  min-height: 64px;
  height: 64px;
}

/* Cancel Button styling */
.btn-cancel {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--g500);
  border: none;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Outfit', var(--font-sans);
  transition: color 0.15s ease, background-color 0.15s ease;
  padding: 9px 16px;
  border-radius: 10px;
  text-decoration: none;
}
.btn-cancel:hover {
  color: var(--g900);
  background-color: var(--g50);
}

/* Photo upload zone */
.photo-upload-zone {
  border: 2px dashed var(--g200);
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  background: var(--g50);
  transition: border-color 0.15s, background-color 0.15s;
  cursor: pointer;
}

.photo-upload-zone:hover {
  border-color: var(--amber);
  background: #ffffff;
}

.photo-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--g600);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
}

.photo-upload-label svg {
  width: 32px;
  height: 32px;
  color: var(--g400);
}

.photo-upload-subtext {
  font-size: 0.72rem;
  color: var(--g400);
  font-weight: 400;
}

/* Local photo preview grid */
.photo-previews-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.photo-preview-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--g100);
}

.photo-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-preview-delete {
  position: absolute;
  top: 4px;
  right: 4px;
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
  border: none;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s;
}

.photo-preview-delete:hover {
  background: rgba(239, 68, 68, 0.9);
}

.photo-preview-delete svg {
  width: 10px;
  height: 10px;
}
</style>
