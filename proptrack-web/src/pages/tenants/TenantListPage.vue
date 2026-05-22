<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const { tenants, meta, filters, isLoading, error, fetchTenants, applyFilters, changePage, resetFilters } = useTenant()
const authStore = useAuthStore()

const searchInput = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const canCreate = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const statusTabs = [
  { label: 'All', value: '' },
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
] as const

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

function onStatusFilter(status: 'active' | 'inactive' | '') {
  applyFilters({ status })
}

function formatKtp(ktp: string): string {
  if (!ktp || ktp.length !== 16) return ktp
  return `${ktp.slice(0, 4)} **** **** ${ktp.slice(12)}`
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

    <!-- Quick filter tabs -->
    <div class="tab-bar">
      <button v-for="tab in statusTabs" :key="tab.value"
        :class="['tab', { 'tab--active': (filters.status || '') === tab.value }]"
        @click="onStatusFilter(tab.value)">
        {{ tab.label }}
      </button>
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
      <p class="empty-state__text">{{ searchInput || filters.status ? 'Try a different search term or filter.' : 'Add your first tenant to get started.' }}</p>
      <RouterLink v-if="canCreate && !searchInput && !filters.status" to="/tenants/new" class="btn-primary" style="margin-top:4px">Add tenant</RouterLink>
    </div>

    <div v-else class="card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 25%">Name</th>
            <th style="width: 23%">Email</th>
            <th style="width: 15%">Phone</th>
            <th style="width: 17%">KTP</th>
            <th style="width: 10%">Contract</th>
            <th style="width: 8%">Added</th>
            <th style="width: 2%"></th>
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
            <td class="ktp-cell">{{ formatKtp(tenant.id_card_number) }}</td>
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

      <!-- Table Footer Counters -->
      <div class="table-footer">
        <span>Showing {{ tenants.length }} of {{ meta?.total || tenants.length }} tenants</span>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination" style="margin-top: 20px;">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="pagination__info">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>

    <!-- Onboarding growth card (only shown when there is very little tenant data) -->
    <div v-if="!isLoading && meta && meta.total > 0 && meta.total < 3" class="tenant-tip-card">
      <div class="tip-card__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A5 5 0 0 0 8 8c0 1 .3 2.5 1.5 3.5.7.8 1.3 1.5 1.5 2.5"/>
          <path d="M9 18h6M10 22h4"/>
        </svg>
      </div>
      <div class="tip-card__content">
        <h4 class="tip-card__title">Streamline Your Property Management</h4>
        <p class="tip-card__text">
          Add all active tenants to easily track monthly rent contracts, automatically generate invoices, dispatch instant WhatsApp notifications, and receive repair tickets.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tenant-row {
  cursor: pointer;
  transition: background 0.15s ease;
}

.tenant-row td {
  transition: background 0.15s ease, color 0.15s ease;
}

.tenant-row:hover td {
  background: var(--g50);
}

.tenant-row:hover .row-btn {
  color: var(--g900);
  background: var(--g100);
  transform: translateX(2px);
}

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

.ktp-cell {
  font-family: monospace;
  font-size: 0.8rem;
  color: var(--g500);
  white-space: nowrap;
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
  transition: color 0.15s ease, background 0.15s ease, transform 0.15s ease;
}

.row-btn svg {
  width: 16px;
  height: 16px;
}

.table-footer {
  padding: 14px 20px;
  background: var(--g25, #fafafa);
  border-top: 1px solid var(--g100);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.76rem;
  color: var(--g400);
  font-weight: 500;
}

.tenant-tip-card {
  margin-top: 24px;
  padding: 18px 20px;
  background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(243,244,246,0.5));
  border: 1px dashed var(--g200);
  border-radius: 16px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  box-shadow: 0 4px 20px -2px rgba(0,0,0,0.02);
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.tenant-tip-card:hover {
  transform: translateY(-2px);
  border-color: var(--g400);
}

.tip-card__icon {
  width: 36px;
  height: 36px;
  background: var(--g900);
  color: #fff;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.tip-card__icon svg {
  width: 18px;
  height: 18px;
}

.tip-card__content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tip-card__title {
  font-size: 0.84rem;
  font-weight: 600;
  color: var(--g900);
  margin: 0;
}

.tip-card__text {
  font-size: 0.78rem;
  color: var(--g500);
  line-height: 1.5;
  margin: 0;
}
</style>
