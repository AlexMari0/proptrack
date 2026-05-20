<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Property } from '@/types/property'

const props = defineProps<{
  property: Property
}>()

const router = useRouter()

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
      <div v-else class="property-card__placeholder">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
          <path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h3" />
        </svg>
        <span>No Photo</span>
      </div>

      <!-- Type badge -->
      <span class="property-card__type-badge">{{ typeLabel }}</span>
    </div>

    <!-- Content -->
    <div class="property-card__body">
      <div class="property-card__header">
        <h3 class="property-card__name">{{ property.name }}</h3>
        <span :class="['property-card__status', statusConfig.class]">
          {{ statusConfig.label }}
        </span>
      </div>

      <p class="property-card__address">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd" />
        </svg>
        {{ property.address }}
      </p>

      <div class="property-card__footer">
        <span class="property-card__price">{{ formattedPrice }}<span class="property-card__price-unit">/mo</span></span>
        <span class="property-card__photos-count">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.69l-2.22-2.219a.75.75 0 00-1.06 0l-1.91 1.909.47.47a.75.75 0 11-1.06 1.06L6.53 8.091a.75.75 0 00-1.06 0l-3 3v-.03zm13-8.061H3.25a.75.75 0 00-.75.75V11.44l2.47-2.47a2.25 2.25 0 013.182 0l.53.53 1.38-1.38a2.25 2.25 0 013.182 0l2.216 2.216V5.25a.75.75 0 00-.75-.75z" clip-rule="evenodd" />
          </svg>
          {{ property.photos.length }}
        </span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.property-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.property-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
  border-color: var(--color-primary);
}

/* Image */
.property-card__image {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--color-surface-alt);
}

.property-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.property-card:hover .property-card__img {
  transform: scale(1.04);
}

.property-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.property-card__placeholder svg {
  width: 40px;
  height: 40px;
  opacity: 0.4;
}

.property-card__type-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(8px);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 20px;
}

/* Body */
.property-card__body {
  padding: 16px;
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
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.3;
  flex: 1;
  margin: 0;
}

/* Status badges */
.property-card__status {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
}

.badge-available {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}

.badge-occupied {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.badge-maintenance {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}

/* Address */
.property-card__address {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 0.78rem;
  color: var(--color-text-muted);
  margin: 0 0 12px;
  line-height: 1.4;
}

.property-card__address svg {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  margin-top: 1px;
}

/* Footer */
.property-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.property-card__price {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-primary);
}

.property-card__price-unit {
  font-size: 0.72rem;
  font-weight: 400;
  color: var(--color-text-muted);
  margin-left: 2px;
}

.property-card__photos-count {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.property-card__photos-count svg {
  width: 14px;
  height: 14px;
}
</style>
