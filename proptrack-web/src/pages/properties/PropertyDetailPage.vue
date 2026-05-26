<script setup lang="ts">
import { onMounted, onUnmounted, watch, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProperty } from '@/composables/useProperty'
import { useAuthStore } from '@/stores/auth'
import PropertyGallery from '@/components/property/PropertyGallery.vue'
import type { Map as LeafletMap } from 'leaflet'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedProperty, isLoading, isSubmitting, error, fetchProperty, deleteProperty, uploadPhoto, deletePhoto } = useProperty()

const mapContainer = ref<HTMLElement | null>(null)
let leafletMap: LeafletMap | null = null

const propertyId = computed(() => route.params.id as string)

const canManage = computed(() => {
  if (!selectedProperty.value || !authStore.user) return false
  const userId = String(authStore.user.id).toLowerCase()
  const ownerId = String(selectedProperty.value.owner.id).toLowerCase()
  const roles = authStore.user.roles || []
  return userId === ownerId || roles.includes('admin') || roles.includes('agent')
})

const statusBadgeClass = computed(() => {
  const map = { available: 'badge badge--green', occupied: 'badge badge--amber', maintenance: 'badge badge--gray' }
  return selectedProperty.value ? (map[selectedProperty.value.status] ?? 'badge badge--gray') : ''
})

const formattedPrice = computed(() => {
  if (!selectedProperty.value) return ''
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(selectedProperty.value.monthly_price)
})

async function initMap() {
  if (!mapContainer.value || !selectedProperty.value) return
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')
  delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
  })
  const { latitude, longitude, name, address } = selectedProperty.value
  leafletMap = L.map(mapContainer.value).setView([latitude, longitude], 15)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>', maxZoom: 19,
  }).addTo(leafletMap)
  L.marker([latitude, longitude]).addTo(leafletMap).bindPopup(`<strong>${name}</strong><br>${address}`).openPopup()
}

function destroyMap() {
  if (leafletMap) { leafletMap.remove(); leafletMap = null }
}

async function handleDelete() {
  if (!confirm('Are you sure you want to delete this property? This cannot be undone.')) return
  await deleteProperty(propertyId.value)
}

async function handleUploadPhoto(file: File) { await uploadPhoto(propertyId.value, file) }
async function handleDeletePhoto(mediaId: number) { await deletePhoto(propertyId.value, mediaId) }


onMounted(async () => { await fetchProperty(propertyId.value) })

watch(selectedProperty, async (property) => {
  if (property) {
    destroyMap()
    await new Promise((r) => setTimeout(r, 50))
    initMap()
  }
})

onUnmounted(() => { destroyMap() })
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back to properties
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="sk-layout">
      <div class="shimmer" style="height:300px;border-radius:14px" />
      <div class="sk-side">
        <div class="shimmer" style="height:80px;border-radius:12px" v-for="i in 4" :key="i" />
      </div>
    </div>

    <div v-else-if="selectedProperty">
      <!-- Header -->
      <div class="page-header">
        <div>
          <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
            <span class="badge badge--gray" style="text-transform:capitalize">{{ selectedProperty.type }}</span>
            <span :class="statusBadgeClass" style="text-transform:capitalize">{{ selectedProperty.status }}</span>
          </div>
          <h1 class="page-title">{{ selectedProperty.name }}</h1>
          <p class="page-subtitle" style="display:flex;align-items:center;gap:4px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:14px;height:14px;flex-shrink:0" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ selectedProperty.address }}
          </p>
        </div>
        <div v-if="canManage" style="display:flex;gap:10px">
          <RouterLink :to="{ name: 'property-edit', params: { id: selectedProperty.id } }" class="btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </RouterLink>
          <button class="btn-danger" :disabled="isSubmitting" @click="handleDelete">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Main grid -->
      <div class="prop-grid">
        <!-- Info sidebar -->
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="card">
            <p class="section-label">Property info</p>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:12px">
              <div class="info-item">
                <span class="info-item__label">Monthly price</span>
                <span class="tabular-nums" style="font-size:1.3rem;font-weight:800;color:var(--g900)">{{ formattedPrice }}</span>
              </div>
              <div class="info-item">
                <span class="info-item__label">Owner</span>
                <span style="font-weight:600;color:var(--g700)">{{ selectedProperty.owner.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-item__label">Coordinates</span>
                <span style="font-family:monospace;font-size:0.78rem;color:var(--g500)">{{ selectedProperty.latitude.toFixed(6) }}, {{ selectedProperty.longitude.toFixed(6) }}</span>
              </div>
              <div v-if="selectedProperty.description" class="info-item">
                <span class="info-item__label">Description</span>
                <p style="font-size:0.875rem;color:var(--g600);line-height:1.55;margin:0">{{ selectedProperty.description }}</p>
              </div>
              <div class="info-item">
                <span class="info-item__label">Added</span>
                <span style="font-size:0.8rem;color:var(--g400)">{{ new Date(selectedProperty.created_at).toLocaleDateString('id-ID', { year:'numeric', month:'long', day:'numeric' }) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Map -->
        <div class="card" style="padding:0;overflow:hidden">
          <div style="padding:16px 20px 12px;border-bottom:1px solid var(--g100)">
            <p class="section-label" style="margin:0">Location</p>
          </div>
          <div ref="mapContainer" class="prop-map" />
        </div>
      </div>

      <!-- Gallery -->
      <div class="card" style="margin-top:16px">
        <p class="section-label" style="margin-bottom:12px">Photos ({{ selectedProperty.photos.length }})</p>
        <div>
          <PropertyGallery
            :photos="selectedProperty.photos"
            :property-id="selectedProperty.id"
            :can-manage="canManage"
            :is-uploading="isSubmitting"
            @upload="handleUploadPhoto"
            @delete="handleDeletePhoto"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sk-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.sk-side { display: flex; flex-direction: column; gap: 12px; }

.prop-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 16px;
  align-items: start;
}
@media (max-width: 860px) { .prop-grid { grid-template-columns: 1fr; } }

.prop-map { height: 360px; }

.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-item__label { font-size: 0.7rem; font-weight: 600; color: var(--g400); text-transform: uppercase; letter-spacing: 0.04em; }
</style>
