<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
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
</script>

<template>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Tenants</h1>
        <p class="page-subtitle">{{ meta ? `${meta.total} tenants registered` : 'Manage tenant profiles' }}</p>
      </div>
      <RouterLink v-if="canCreate" to="/tenants/new" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Add tenant
      </RouterLink>
    </div>

    <div class="filter-bar">
      <div class="search-field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input v-model="searchInput" type="search" placeholder="Search by name or email…" aria-label="Search tenants" />
      </div>
      <button v-if="searchInput" class="btn-ghost" @click="onReset">Clear</button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="card" style="padding:0;overflow:hidden">
      <div v-for="i in 6" :key="i" class="shimmer" style="height:56px;border-radius:0;margin-bottom:1px" />
    </div>

    <div v-else-if="!isLoading && tenants.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <p class="empty-state__title">No tenants found</p>
      <p class="empty-state__text">{{ searchInput ? 'Try a different search term.' : 'Add your first tenant to get started.' }}</p>
      <RouterLink v-if="canCreate && !searchInput" to="/tenants/new" class="btn-primary" style="margin-top:4px">Add tenant</RouterLink>
    </div>

    <div v-else class="card" style="padding:0;overflow:hidden">
      <table class="data-table">
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
          <tr v-for="tenant in tenants" :key="tenant.id" class="tenant-row" @click="router.push({ name: 'tenant-detail', params: { id: tenant.id } })">
            <td>
              <div class="tenant-name">
                <span class="tenant-avatar" aria-hidden="true">{{ tenant.name.charAt(0).toUpperCase() }}</span>
                <span style="font-weight:500;color:var(--g900)">{{ tenant.name }}</span>
              </div>
            </td>
            <td style="color:var(--g500)">{{ tenant.email }}</td>
            <td style="color:var(--g500)">{{ tenant.phone }}</td>
            <td style="font-family:monospace;font-size:0.8rem;color:var(--g500)">{{ tenant.id_card_number }}</td>
            <td>
              <span v-if="tenant.active_contract" class="badge badge--green">Active</span>
              <span v-else class="badge badge--gray">No contract</span>
            </td>
            <td style="color:var(--g400);font-size:0.78rem">{{ new Date(tenant.created_at).toLocaleDateString('id-ID') }}</td>
            <td>
              <button class="row-btn" @click.stop="router.push({ name: 'tenant-detail', params: { id: tenant.id } })" aria-label="View tenant">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
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
.tenant-row { cursor: pointer; }

.tenant-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tenant-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--g900);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 700;
  flex-shrink: 0;
}

.row-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--g400);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: color 0.15s, background 0.15s;
}
.row-btn:hover { color: var(--g900); background: var(--g100); }
.row-btn svg { width: 16px; height: 16px; }
</style>
