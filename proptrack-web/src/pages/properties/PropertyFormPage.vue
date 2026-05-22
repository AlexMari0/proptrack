<script setup lang="ts">
import { onMounted, computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProperty } from '@/composables/useProperty'
import type { PropertyType, PropertyStatus, StorePropertyPayload } from '@/types/property'

const route = useRoute()
const router = useRouter()
const { selectedProperty, isLoading, isSubmitting, error, fetchProperty, createProperty, updateProperty } =
  useProperty()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => (isEditMode.value ? 'Edit Property' : 'Add Property'))

// ─── Form State ────────────────────────────────────────────────────────────────

const form = reactive<StorePropertyPayload>({
  name: '',
  address: '',
  type: 'kos',
  status: 'available',
  latitude: 0,
  longitude: 0,
  description: '',
  monthly_price: 0,
})

const errors = reactive<Partial<Record<keyof StorePropertyPayload, string>>>({})

// ─── Populate form on edit mode ────────────────────────────────────────────────

onMounted(async () => {
  if (isEditMode.value) {
    await fetchProperty(route.params.id as string)
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
  }
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
  if (form.latitude < -90 || form.latitude > 90) { errors.latitude = 'Latitude must be between -90 and 90.'; valid = false }
  if (form.longitude < -180 || form.longitude > 180) { errors.longitude = 'Longitude must be between -180 and 180.'; valid = false }
  if (form.monthly_price < 0) { errors.monthly_price = 'Price must be 0 or greater.'; valid = false }

  return valid
}

// ─── Submit ────────────────────────────────────────────────────────────────────

async function handleSubmit() {
  if (!validate()) return

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

  if (isEditMode.value) {
    await updateProperty(route.params.id as string, payload)
  } else {
    await createProperty(payload)
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

    <!-- Server-side error -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="shimmer" style="height: 380px; border-radius: 16px;" />

    <form v-else @submit.prevent="handleSubmit" novalidate>
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

        <!-- Address -->
        <div class="field">
          <label class="form-label" for="address">Address <span style="color: #dc2626;">*</span></label>
          <textarea
            id="address"
            v-model="form.address"
            class="form-textarea"
            :class="{ 'input-error': errors.address }"
            placeholder="e.g. Jl. Harmoni No. 12, Jakarta Pusat"
            rows="2"
          />
          <span v-if="errors.address" class="form-error">{{ errors.address }}</span>
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

        <!-- Latitude & Longitude row -->
        <div class="field-row">
          <div class="field">
            <label class="form-label" for="latitude">Latitude <span style="color: #dc2626;">*</span></label>
            <input
              id="latitude"
              v-model="form.latitude"
              type="number"
              step="any"
              class="form-input"
              :class="{ 'input-error': errors.latitude }"
              placeholder="-6.1751"
            />
            <span v-if="errors.latitude" class="form-error">{{ errors.latitude }}</span>
          </div>

          <div class="field">
            <label class="form-label" for="longitude">Longitude <span style="color: #dc2626;">*</span></label>
            <input
              id="longitude"
              v-model="form.longitude"
              type="number"
              step="any"
              class="form-input"
              :class="{ 'input-error': errors.longitude }"
              placeholder="106.8272"
            />
            <span v-if="errors.longitude" class="form-error">{{ errors.longitude }}</span>
          </div>
        </div>

        <!-- Monthly Price -->
        <div class="field">
          <label class="form-label" for="monthly_price">Monthly Price (IDR) <span style="color: #dc2626;">*</span></label>
          <div class="prefix-group">
            <span class="prefix-text">Rp</span>
            <input
              id="monthly_price"
              v-model="form.monthly_price"
              type="number"
              min="0"
              class="form-input form-input--prefixed"
              :class="{ 'input-error': errors.monthly_price }"
              placeholder="1500000"
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

      <!-- Actions -->
      <div style="display: flex; gap: 10px;">
        <button type="submit" class="btn-primary" :disabled="isSubmitting">
          <span v-if="isSubmitting" class="spinner" />
          {{ isEditMode ? 'Save Changes' : 'Create Property' }}
        </button>
        <button type="button" class="btn-ghost" @click="router.back()">Cancel</button>
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
</style>
