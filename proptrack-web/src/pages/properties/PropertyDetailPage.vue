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
const {
  selectedProperty,
  isLoading,
  isSubmitting,
  error,
  fetchProperty,
  deleteProperty,
  uploadPhoto,
  deletePhoto,
} = useProperty()

const mapContainer = ref<HTMLElement | null>(null)
let leafletMap: LeafletMap | null = null

const propertyId = computed(() => route.params.id as string)

const canManage = computed(() => {
  if (!selectedProperty.value || !authStore.user) return false
  return (
    String(authStore.user.id) === selectedProperty.value.owner.id ||
    authStore.user.roles?.includes('admin')
  )
})

const statusConfig = computed(() => {
  const map = {
    available: { label: 'Available', class: 'badge-available' },
    occupied: { label: 'Occupied', class: 'badge-occupied' },
    maintenance: { label: 'Maintenance', class: 'badge-maintenance' },
  }
  return selectedProperty.value ? map[selectedProperty.value.status] : null
})

const formattedPrice = computed(() => {
  if (!selectedProperty.value) return ''
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(selectedProperty.value.monthly_price)
})

// ─── Map ───────────────────────────────────────────────────────────────────────

async function initMap() {
  if (!mapContainer.value || !selectedProperty.value) return

  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')

  // Fix default marker icon paths broken by Vite bundling
  delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
  })

  const { latitude, longitude, name, address } = selectedProperty.value

  leafletMap = L.map(mapContainer.value).setView([latitude, longitude], 15)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(leafletMap)

  L.marker([latitude, longitude])
    .addTo(leafletMap)
    .bindPopup(`<strong>${name}</strong><br>${address}`)
    .openPopup()
}

function destroyMap() {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
  }
}

// ─── Actions ──────────────────────────────────────────────────────────────────

async function handleDelete() {
  if (!confirm('Are you sure you want to delete this property? This cannot be undone.')) return
  await deleteProperty(propertyId.value)
}

async function handleUploadPhoto(file: File) {
  await uploadPhoto(propertyId.value, file)
}

async function handleDeletePhoto(mediaId: number) {
  await deletePhoto(propertyId.value, mediaId)
}

// ─── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  await fetchProperty(propertyId.value)
})

// Init map once property data is loaded
watch(selectedProperty, async (property) => {
  if (property) {
    destroyMap()
    // Small tick so the DOM element is rendered
    await new Promise((r) => setTimeout(r, 50))
    initMap()
  }
})

onUnmounted(() => {
  destroyMap()
})
</script>

<template>
  <div class="page">
    <!-- Back -->
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back to Properties
    </button>

    <!-- Error -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="isLoading" class="skeleton-detail">
      <div class="skeleton-detail__gallery" />
      <div class="skeleton-detail__content">
        <div class="skeleton-line skeleton-line--title" />
        <div class="skeleton-line" />
        <div class="skeleton-line skeleton-line--short" />
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="selectedProperty" class="detail">
      <!-- Title row -->
      <div class="detail__header">
        <div class="detail__title-group">
          <div class="detail__badges">
            <span class="badge-type">{{ selectedProperty.type }}</span>
            <span v-if="statusConfig" :class="['badge-status', statusConfig.class]">
              {{ statusConfig.label }}
            </span>
          </div>
          <h1 class="detail__title">{{ selectedProperty.name }}</h1>
          <p class="detail__address">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" />
            </svg>
            {{ selectedProperty.address }}
          </p>
        </div>

        <div v-if="canManage" class="detail__actions">
          <RouterLink
            :to="{ name: 'property-edit', params: { id: selectedProperty.id } }"
            class="btn btn--ghost"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
              <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
            </svg>
            Edit
          </RouterLink>
          <button class="btn btn--danger" :disabled="isSubmitting" @click="handleDelete">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4z" clip-rule="evenodd" />
            </svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Main grid: info + map -->
      <div class="detail__grid">
        <!-- Left: info -->
        <div class="detail__info">
          <!-- Price card -->
          <div class="info-card">
            <span class="info-card__label">Monthly Price</span>
            <span class="info-card__value info-card__value--price">{{ formattedPrice }}</span>
          </div>

          <!-- Owner -->
          <div class="info-card">
            <span class="info-card__label">Owner</span>
            <span class="info-card__value">{{ selectedProperty.owner.name }}</span>
          </div>

          <!-- Coordinates -->
          <div class="info-card">
            <span class="info-card__label">Coordinates</span>
            <span class="info-card__value info-card__value--mono">
              {{ selectedProperty.latitude.toFixed(6) }}, {{ selectedProperty.longitude.toFixed(6) }}
            </span>
          </div>

          <!-- Description -->
          <div v-if="selectedProperty.description" class="info-card">
            <span class="info-card__label">Description</span>
            <p class="info-card__value info-card__value--body">{{ selectedProperty.description }}</p>
          </div>

          <!-- Added date -->
          <div class="info-card">
            <span class="info-card__label">Added</span>
            <span class="info-card__value">
              {{ new Date(selectedProperty.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
            </span>
          </div>
        </div>

        <!-- Right: map -->
        <div class="detail__map-wrapper">
          <h2 class="section-title">Location</h2>
          <div ref="mapContainer" class="detail__map" />
        </div>
      </div>

      <!-- Gallery -->
      <div class="detail__gallery-section">
        <h2 class="section-title">Photos ({{ selectedProperty.photos.length }})</h2>
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
</template>

<style scoped>
.page { padding: 32px; max-width: 1280px; margin: 0 auto; }

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  cursor: pointer;
  padding: 0;
  margin-bottom: 24px;
  transition: color 0.2s;
}
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

/* Header */
.detail__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}

.detail__badges {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}

.badge-type {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 12px;
  border-radius: 20px;
  background: rgba(99, 102, 241, 0.12);
  color: var(--color-primary);
}

.badge-status {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 4px 12px;
  border-radius: 20px;
}
.badge-available { background: rgba(34,197,94,0.15); color: #22c55e; }
.badge-occupied   { background: rgba(239,68,68,0.15); color: #ef4444; }
.badge-maintenance { background: rgba(245,158,11,0.15); color: #f59e0b; }

.detail__title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); margin: 0 0 8px; }

.detail__address {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin: 0;
}
.detail__address svg { width: 16px; height: 16px; flex-shrink: 0; }

.detail__actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* Main grid */
.detail__grid {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 24px;
  margin-bottom: 40px;
}

@media (max-width: 768px) { .detail__grid { grid-template-columns: 1fr; } }

/* Info cards */
.detail__info { display: flex; flex-direction: column; gap: 12px; }

.info-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-card__label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.info-card__value { font-size: 0.9rem; color: var(--color-text); font-weight: 500; }
.info-card__value--price { font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.info-card__value--mono { font-family: monospace; font-size: 0.82rem; }
.info-card__value--body { margin: 0; line-height: 1.6; font-weight: 400; }

/* Map */
.section-title { font-size: 1rem; font-weight: 600; color: var(--color-text); margin: 0 0 12px; }

.detail__map-wrapper { display: flex; flex-direction: column; }

.detail__map {
  flex: 1;
  min-height: 340px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid var(--color-border);
  z-index: 0;
}

/* Gallery section */
.detail__gallery-section { margin-top: 8px; }

/* Skeletons */
.skeleton-detail { display: grid; grid-template-columns: 1fr 1.4fr; gap: 24px; }
.skeleton-detail__gallery { aspect-ratio: 16/9; border-radius: 16px; }
.skeleton-detail__content { display: flex; flex-direction: column; gap: 16px; padding: 16px 0; }
.skeleton-line { height: 20px; border-radius: 6px; }
.skeleton-line--title { height: 36px; width: 70%; }
.skeleton-line--short { width: 40%; }
.skeleton-line, .skeleton-detail__gallery {
  background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer { to { background-position: -200% 0; } }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px; border-radius: 10px;
  font-size: 0.875rem; font-weight: 600; cursor: pointer;
  border: none; text-decoration: none; transition: all 0.2s;
}
.btn svg { width: 16px; height: 16px; }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn--danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }
.btn--danger:hover { background: rgba(239,68,68,0.2); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
