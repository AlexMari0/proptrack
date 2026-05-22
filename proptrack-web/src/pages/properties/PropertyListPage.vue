<script setup lang="ts">
import { onMounted, watch, ref } from 'vue'
import { useProperty } from '@/composables/useProperty'
import { useAuthStore } from '@/stores/auth'
import PropertyCard from '@/components/property/PropertyCard.vue'
import type { PropertyStatus, PropertyType } from '@/types/property'

const { properties, meta, filters, isLoading, error, fetchProperties, applyFilters, changePage, resetFilters } =
  useProperty()
const authStore = useAuthStore()

const searchInput = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
  fetchProperties()
})

watch(searchInput, (value) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters({ search: value })
  }, 400)
})

function onStatusFilter(status: PropertyStatus | '') {
  applyFilters({ status })
}

function onTypeFilter(type: PropertyType | '') {
  applyFilters({ type })
}

function onReset() {
  searchInput.value = ''
  resetFilters()
  fetchProperties()
}

const canCreate = ['owner', 'admin'].some((role) =>
  authStore.user?.roles?.includes(role),
)

const statusTabs = [
  { label: 'All', value: '' },
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' },
] as const

const typeOptions = [
  { label: 'All types', value: '' },
  { label: 'Kos', value: 'kos' },
  { label: 'Apartment', value: 'apartment' },
  { label: 'Ruko', value: 'ruko' },
] as const
</script>

<template>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Properties</h1>
        <p class="page-subtitle">{{ meta ? `${meta.total} properties found` : 'Manage your portfolio' }}</p>
      </div>
      <RouterLink v-if="canCreate" to="/properties/new" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Add property
      </RouterLink>
    </div>

    <div class="filter-bar">
      <div class="search-field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input v-model="searchInput" type="search" placeholder="Search by name or address…" aria-label="Search properties" />
      </div>
      <select class="form-select" style="width:auto;min-width:130px" :value="filters.type" @change="onTypeFilter(($event.target as HTMLSelectElement).value as PropertyType | '')">
        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <button v-if="filters.search || filters.status || filters.type" class="btn-ghost" @click="onReset">Reset</button>
    </div>

    <div class="tab-bar">
      <button v-for="tab in statusTabs" :key="tab.value"
        :class="['tab', { 'tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as PropertyStatus | '')">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="prop-grid">
      <div v-for="i in 8" :key="i" class="shimmer" style="aspect-ratio:1/1.05;border-radius:16px;" />
    </div>

    <div v-else-if="!isLoading && properties.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <p class="empty-state__title">No properties found</p>
      <p class="empty-state__text">Try adjusting your filters or add your first property.</p>
      <RouterLink v-if="canCreate" to="/properties/new" class="btn-primary" style="margin-top:4px">Add property</RouterLink>
    </div>

    <div v-else class="prop-grid">
      <PropertyCard v-for="property in properties" :key="property.id" :property="property" />
    </div>

    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="pagination__info">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.prop-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 24px;
  margin-bottom: 28px;
}
</style>
