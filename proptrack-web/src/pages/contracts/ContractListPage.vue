<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useContract } from '@/composables/useContract'
import { useAuthStore } from '@/stores/auth'
import ContractCard from '@/components/tenant/ContractCard.vue'
import type { ContractStatus } from '@/types/contract'

const authStore = useAuthStore()
const { contracts, meta, filters, isLoading, error, fetchContracts, applyFilters, changePage, terminateContract, downloadDocument } = useContract()

const canCreate = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const statusTabs = [
  { label: 'All',        value: '' },
  { label: 'Active',     value: 'active' },
  { label: 'Expired',    value: 'expired' },
  { label: 'Terminated', value: 'terminated' },
] as const

onMounted(() => fetchContracts())

function onStatusFilter(status: ContractStatus | '') {
  applyFilters({ status })
}

async function handleTerminate(id: string) {
  if (!confirm('Terminate this contract? This cannot be undone.')) return
  await terminateContract(id)
  await fetchContracts()
}

async function handleDownload(id: string) {
  await downloadDocument(id)
}
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page__header">
      <div>
        <h1 class="page__title">Contracts</h1>
        <p class="page__subtitle">
          {{ meta ? `${meta.total} contracts total` : 'Manage rental agreements' }}
        </p>
      </div>
      <RouterLink v-if="canCreate" to="/contracts/new" class="btn btn--primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
        </svg>
        New Contract
      </RouterLink>
    </div>

    <!-- Status tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        :class="['status-tab', { 'status-tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as ContractStatus | '')"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Loading skeletons -->
    <div v-if="isLoading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-item" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!isLoading && contracts.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0016.5 9h-1.875a1.875 1.875 0 01-1.875-1.875V5.25A3.75 3.75 0 009 1.5H5.625zM7.5 15a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5A.75.75 0 017.5 15zm.75-6.75a.75.75 0 000 1.5H12a.75.75 0 000-1.5H8.25z" clip-rule="evenodd" />
      </svg>
      <h3>No contracts found</h3>
      <p>{{ filters.status ? `No ${filters.status} contracts.` : 'Create your first rental contract.' }}</p>
      <RouterLink v-if="canCreate && !filters.status" to="/contracts/new" class="btn btn--primary">
        New Contract
      </RouterLink>
    </div>

    <!-- List -->
    <div v-else class="contract-list">
      <ContractCard
        v-for="contract in contracts"
        :key="contract.id"
        :contract="contract"
        :show-actions="canCreate"
        @terminate="handleTerminate"
        @download="handleDownload"
      />
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
.page { padding: 32px; max-width: 900px; margin: 0 auto; }
.page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.page__title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.page__subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

.status-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.status-tab { padding: 6px 16px; border-radius: 20px; border: 1px solid var(--color-border); background: transparent; color: var(--color-text-muted); font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.status-tab:hover { border-color: var(--color-primary); color: var(--color-primary); }
.status-tab--active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

.contract-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 28px; }

.skeleton-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 28px; }
.skeleton-item { height: 110px; border-radius: 14px; background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 80px 24px; text-align: center; color: var(--color-text-muted); }
.empty-state svg { width: 56px; height: 56px; opacity: 0.3; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: var(--color-text); margin: 0; }
.empty-state p { font-size: 0.875rem; margin: 0; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; }
.pagination__btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.pagination__btn:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.pagination__btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination__btn svg { width: 18px; height: 18px; }
.pagination__info { font-size: 0.875rem; color: var(--color-text-muted); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn svg { width: 18px; height: 18px; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover { background: var(--color-primary-hover); }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
