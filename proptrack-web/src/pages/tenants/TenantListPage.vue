<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const { tenants, meta, isLoading, error, fetchTenants, applyFilters, changePage, resetFilters } = useTenant()
const authStore = useAuthStore()

const searchInput = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const canCreate = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

onMounted(() => fetchTenants())

watch(searchInput, (value) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters({ search: value }), 400)
})

function onReset() {
  searchInput.value = ''
  resetFilters()
  fetchTenants()
}

import { computed } from 'vue'
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page__header">
      <div>
        <h1 class="page__title">Tenants</h1>
        <p class="page__subtitle">
          {{ meta ? `${meta.total} tenants registered` : 'Manage your tenant profiles' }}
        </p>
      </div>
      <RouterLink v-if="canCreate" to="/tenants/new" class="btn btn--primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
        </svg>
        Add Tenant
      </RouterLink>
    </div>

    <!-- Search -->
    <div class="search-row">
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
        <input
          v-model="searchInput"
          type="text"
          placeholder="Search by name or email…"
          class="search-box__input"
        />
        <button v-if="searchInput" class="search-box__clear" @click="searchInput = ''; onReset()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Loading skeletons -->
    <div v-if="isLoading" class="table-skeleton">
      <div v-for="i in 6" :key="i" class="table-skeleton__row" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!isLoading && tenants.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
      </svg>
      <h3>No tenants found</h3>
      <p>{{ searchInput ? 'Try a different search term.' : 'Add your first tenant to get started.' }}</p>
      <RouterLink v-if="canCreate && !searchInput" to="/tenants/new" class="btn btn--primary">
        Add Tenant
      </RouterLink>
    </div>

    <!-- Table -->
    <div v-else class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>KTP</th>
            <th>Contract</th>
            <th>Added</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="tenant in tenants"
            :key="tenant.id"
            class="table__row"
            @click="router.push({ name: 'tenant-detail', params: { id: tenant.id } })"
          >
            <td>
              <div class="tenant-name">
                <span class="tenant-name__avatar">{{ tenant.name.charAt(0).toUpperCase() }}</span>
                <span>{{ tenant.name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ tenant.email }}</td>
            <td class="text-muted">{{ tenant.phone }}</td>
            <td class="text-mono text-muted">{{ tenant.id_card_number }}</td>
            <td>
              <span v-if="tenant.active_contract" class="badge badge--active">Active</span>
              <span v-else class="badge badge--none">No Contract</span>
            </td>
            <td class="text-muted text-sm">
              {{ new Date(tenant.created_at).toLocaleDateString('id-ID') }}
            </td>
            <td>
              <button
                class="row-action"
                @click.stop="router.push({ name: 'tenant-detail', params: { id: tenant.id } })"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
        </svg>
      </button>
      <span class="pagination__info">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 1280px; margin: 0 auto; }

.page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.page__title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.page__subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

/* Search */
.search-row { margin-bottom: 24px; }
.search-box { position: relative; display: flex; align-items: center; max-width: 400px; }
.search-box > svg { position: absolute; left: 12px; width: 18px; height: 18px; color: var(--color-text-muted); pointer-events: none; }
.search-box__input { width: 100%; padding: 10px 36px 10px 38px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface); color: var(--color-text); font-size: 0.875rem; outline: none; transition: border-color 0.2s; }
.search-box__input:focus { border-color: var(--color-primary); }
.search-box__clear { position: absolute; right: 10px; background: none; border: none; color: var(--color-text-muted); cursor: pointer; display: flex; padding: 2px; }
.search-box__clear svg { width: 16px; height: 16px; }

/* Table */
.table-wrapper { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
.table { width: 100%; border-collapse: collapse; }
.table thead tr { border-bottom: 1px solid var(--color-border); }
.table th { padding: 12px 16px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.table__row { cursor: pointer; transition: background 0.15s; }
.table__row:hover { background: var(--color-surface-alt); }
.table__row:not(:last-child) { border-bottom: 1px solid var(--color-border); }
.table td { padding: 14px 16px; font-size: 0.875rem; color: var(--color-text); }
.text-muted { color: var(--color-text-muted) !important; }
.text-mono { font-family: monospace; font-size: 0.8rem !important; }
.text-sm { font-size: 0.8rem !important; }

/* Tenant name with avatar */
.tenant-name { display: flex; align-items: center; gap: 10px; }
.tenant-name__avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), #818cf8);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 700; color: #fff; flex-shrink: 0;
}

/* Badges */
.badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 20px; }
.badge--active { background: rgba(34,197,94,0.15); color: #22c55e; }
.badge--none { background: var(--color-surface-alt); color: var(--color-text-muted); }

/* Row action */
.row-action { background: none; border: none; color: var(--color-text-muted); cursor: pointer; padding: 4px; display: flex; border-radius: 6px; transition: color 0.2s, background 0.2s; }
.row-action:hover { color: var(--color-primary); background: rgba(99,102,241,0.1); }
.row-action svg { width: 18px; height: 18px; }

/* Skeleton */
.table-skeleton { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; overflow: hidden; margin-bottom: 24px; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.table-skeleton__row { height: 48px; border-radius: 8px; background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* Empty state */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 80px 24px; text-align: center; color: var(--color-text-muted); }
.empty-state svg { width: 56px; height: 56px; opacity: 0.3; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: var(--color-text); margin: 0; }
.empty-state p { font-size: 0.875rem; margin: 0; }

/* Pagination */
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; }
.pagination__btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.pagination__btn:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.pagination__btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination__btn svg { width: 18px; height: 18px; }
.pagination__info { font-size: 0.875rem; color: var(--color-text-muted); }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn svg { width: 18px; height: 18px; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover { background: var(--color-primary-hover); }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
