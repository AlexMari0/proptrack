<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProperty } from '@/composables/useProperty'
import type { Property } from '@/types/property'

const props = defineProps<{
  property: Property
}>()

const router = useRouter()
const authStore = useAuthStore()
const { uploadPhoto } = useProperty()

const isUploading = ref(false)

const canUpload = computed(() => {
  if (!authStore.user) return false
  const roles = authStore.user.roles || []
  return roles.includes('admin') || (roles.includes('owner') && String(props.property.owner?.id) === String(authStore.user.id))
})

const statusConfig = computed(() => {
  const map = {
    available: { label: 'Available', class: 'badge-available' },
    occupied: { label: 'Occupied', class: 'badge-occupied' },
    maintenance: { label: 'Maintenance', class: 'badge-maintenance' },
  }
  return map[props.property.status]
})

const typeLabel = computed(() => {
  const map = { kos: 'Kos', apartment: 'Apartment', ruko: 'Ruko' }
  return map[props.property.type]
})

const thumbnailUrl = computed(() =>
  props.property.photos.length > 0 ? props.property.photos[0].thumbnail_url : null,
)

const formattedPrice = computed(() =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
    props.property.monthly_price,
  ),
)

function goToDetail() {
  router.push({ name: 'property-detail', params: { id: props.property.id } })
}

async function handlePhotoUpload(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  isUploading.value = true
  try {
    await uploadPhoto(props.property.id, file)
  } catch (err) {
    console.error('Failed to upload photo:', err)
  } finally {
    isUploading.value = false
    target.value = ''
  }
}
</script>

<template>
  <article class="property-card" @click="goToDetail">
    <!-- Thumbnail -->
    <div class="property-card__image">
      <img
        v-if="thumbnailUrl"
        :src="thumbnailUrl"
        :alt="property.name"
        class="property-card__img"
      />
      <div v-else :class="['property-card__placeholder', { 'property-card__placeholder--uploadable': canUpload }]">
        <div v-if="isUploading" class="property-card__uploading-spinner">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.2" />
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-linecap="round" />
          </svg>
          <span>Uploading...</span>
        </div>
        <template v-else>
          <div class="property-card__placeholder-info">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
              <path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h3" />
            </svg>
            <span>No Photo</span>
          </div>

          <!-- Direct Upload Button -->
          <label v-if="canUpload" class="property-card__upload-btn" @click.stop>
            <input
              type="file"
              accept="image/*"
              style="display: none;"
              @change="handlePhotoUpload"
            />
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Upload Photo
          </label>
        </template>
      </div>

      <!-- Type badge -->
      <span class="property-card__type-badge">{{ typeLabel }}</span>

      <!-- Status badge (moved to top-right corner of card) -->
      <span :class="['property-card__status-badge', statusConfig.class]">
        {{ statusConfig.label }}
      </span>
    </div>

    <!-- Content -->
    <div class="property-card__body">
      <div class="property-card__header">
        <h3 class="property-card__name">{{ property.name }}</h3>
      </div>

      <p class="property-card__address">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" />
        </svg>
        {{ property.address }}
      </p>

      <div class="property-card__footer">
        <span class="property-card__price">{{ formattedPrice }}<span class="property-card__price-unit">/mo</span></span>
        <span class="property-card__photos-count" title="Number of photos">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.69l-2.22-2.219a.75.75 0 00-1.06 0l-1.91 1.909.47.47a.75.75 0 11-1.06 1.06L6.53 8.091a.75.75 0 00-1.06 0l-3 3v-.03zm13-8.061H3.25a.75.75 0 00-.75.75V11.44l2.47-2.47a2.25 2.25 0 013.182 0l.53.53 1.38-1.38a2.25 2.25 0 013.182 0l2.216 2.216V5.25a.75.75 0 00-.75-.75z" clip-rule="evenodd" />
          </svg>
          <span>{{ property.photos.length }} {{ property.photos.length === 1 ? 'photo' : 'photos' }}</span>
        </span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.property-card {
  background: #ffffff;
  border: 1px solid var(--g100);
  border-radius: 16px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 310px;
  box-sizing: border-box;
  box-shadow: 0 2px 6px rgba(26, 23, 18, 0.03);
}

.property-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 36px rgba(26, 23, 18, 0.08);
  border-color: var(--amber);
}

/* Image section */
.property-card__image {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--g50);
  border-bottom: 1px solid var(--g100);
  flex-shrink: 0;
}

.property-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.property-card:hover .property-card__img {
  transform: scale(1.04);
}

/* Reduced size empty photo area layout */
.property-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--g400);
  box-sizing: border-box;
  padding: 12px;
}

.property-card__placeholder-info {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.property-card__placeholder-info svg {
  width: 18px;
  height: 18px;
  color: var(--g400);
}

.property-card__placeholder-info span {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--g500);
}

/* Custom Direct Upload Button */
.property-card__upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #ffffff;
  color: var(--g700);
  border: 1px solid var(--g200);
  border-radius: 8px;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  box-shadow: 0 1px 2px rgba(26, 23, 18, 0.05);
}

.property-card__upload-btn:hover {
  border-color: var(--amber);
  color: var(--g900);
  background: var(--g50);
}

.property-card__upload-btn svg {
  width: 12px;
  height: 12px;
  color: var(--g500);
}

/* Upload spinner */
.property-card__uploading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: var(--g500);
  font-size: 0.72rem;
  font-weight: 500;
}

.property-card__uploading-spinner svg {
  width: 22px;
  height: 22px;
  animation: spin 0.8s linear infinite;
  color: var(--amber);
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Badges */
.property-card__type-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(26, 23, 18, 0.75);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 20px;
  pointer-events: none;
}

/* Status badge (top-right corner alignment) */
.property-card__status-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 20px;
  white-space: nowrap;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  pointer-events: none;
}

.badge-available {
  background: rgba(34, 197, 94, 0.85);
  color: #ffffff;
}

.badge-occupied {
  background: rgba(239, 68, 68, 0.85);
  color: #ffffff;
}

.badge-maintenance {
  background: rgba(224, 156, 26, 0.85);
  color: #ffffff;
}

/* Body */
.property-card__body {
  padding: 16px 20px 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.property-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.property-card__name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--g900);
  line-height: 1.35;
  flex: 1;
  margin: 0;
  letter-spacing: -0.01em;
}

/* Address */
.property-card__address {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 0.78rem;
  color: var(--g500);
  margin: 0 0 16px;
  line-height: 1.45;
}

.property-card__address svg {
  width: 14px;
  height: 14px;
  color: var(--g400);
  flex-shrink: 0;
  margin-top: 2px;
}

/* Footer */
.property-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 14px;
  border-top: 1px solid var(--g100);
  margin-top: auto;
}

.property-card__price {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--g900);
}

.property-card__price-unit {
  font-size: 0.72rem;
  font-weight: 400;
  color: var(--g500);
  margin-left: 2px;
}

.property-card__photos-count {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--g500);
}

.property-card__photos-count svg {
  width: 14px;
  height: 14px;
  color: var(--g400);
}
</style>
