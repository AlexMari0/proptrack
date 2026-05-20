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

// Debounce search input
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
  { label: 'All Types', value: '' },
  { label: 'Kos', value: 'kos' },
  { label: 'Apartment', value: 'apartment' },
  { label: 'Ruko', value: 'ruko' },
] as const
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page__header">
      <div>
        <h1 class="page__title">Properties</h1>
        <p class="page__subtitle">
          {{ meta ? `${meta.total} properties found` : 'Manage your property portfolio' }}
        </p>
      </div>
      <RouterLink v-if="canCreate" to="/properties/new" class="btn btn--primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
        </svg>
        Add Property
      </RouterLink>
    </div>

    <!-- Filters -->
    <div class="filters">
      <!-- Search -->
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
        <input
          v-model="searchInput"
          type="text"
          placeholder="Search by name or address…"
          class="search-box__input"
        />
        <button v-if="searchInput" class="search-box__clear" @click="searchInput = ''">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
          </svg>
        </button>
      </div>

      <!-- Type select -->
      <select class="filter-select" :value="filters.type" @change="onTypeFilter(($event.target as HTMLSelectElement).value as PropertyType | '')">
        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <!-- Reset -->
      <button v-if="filters.search || filters.status || filters.type" class="btn btn--ghost btn--sm" @click="onReset">
        Reset
      </button>
    </div>

    <!-- Status tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        :class="['status-tab', { 'status-tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as PropertyStatus | '')"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="grid">
      <div v-for="i in 8" :key="i" class="skeleton-card" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!isLoading && properties.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z" />
        <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198c.03-.028.061-.056.091-.086L12 5.432z" />
      </svg>
      <h3>No properties found</h3>
      <p>Try adjusting your filters or add your first property.</p>
      <RouterLink v-if="canCreate" to="/properties/new" class="btn btn--primary">
        Add Property
      </RouterLink>
    </div>

    <!-- Property grid -->
    <div v-else class="grid">
      <PropertyCard v-for="property in properties" :key="property.id" :property="property" />
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button
        class="pagination__btn"
        :disabled="meta.current_page <= 1"
        @click="changePage(meta.current_page - 1)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
        </svg>
      </button>

      <span class="pagination__info">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </span>

      <button
        class="pagination__btn"
        :disabled="meta.current_page >= meta.last_page"
        @click="changePage(meta.current_page + 1)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 1280px; margin: 0 auto; }

.page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.page__title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.page__subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

/* Filters */
.filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 200px;
}

.search-box > svg {
  position: absolute;
  left: 12px;
  width: 18px;
  height: 18px;
  color: var(--color-text-muted);
  pointer-events: none;
}

.search-box__input {
  width: 100%;
  padding: 10px 40px 10px 38px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.2s;
}

.search-box__input:focus { border-color: var(--color-primary); }

.search-box__clear {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  display: flex;
  padding: 2px;
}

.search-box__clear svg { width: 16px; height: 16px; }

.filter-select {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  outline: none;
  cursor: pointer;
  transition: border-color 0.2s;
}

.filter-select:focus { border-color: var(--color-primary); }

/* Status tabs */
.status-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.status-tab {
  padding: 6px 16px;
  border-radius: 20px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.status-tab:hover { border-color: var(--color-primary); color: var(--color-primary); }
.status-tab--active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

/* Grid */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

/* Skeleton */
.skeleton-card {
  aspect-ratio: 3/4;
  border-radius: 16px;
  background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

@keyframes shimmer { to { background-position: -200% 0; } }

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 24px;
  text-align: center;
  color: var(--color-text-muted);
}

.empty-state svg { width: 56px; height: 56px; opacity: 0.3; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: var(--color-text); margin: 0; }
.empty-state p { font-size: 0.875rem; margin: 0; }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
}

.pagination__btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.pagination__btn:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.pagination__btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination__btn svg { width: 18px; height: 18px; }

.pagination__info { font-size: 0.875rem; color: var(--color-text-muted); }

/* Shared button styles (fallback if not in global CSS) */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: all 0.2s;
}
.btn svg { width: 18px; height: 18px; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover { background: var(--color-primary-hover); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn--sm { padding: 7px 14px; font-size: 0.8rem; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
