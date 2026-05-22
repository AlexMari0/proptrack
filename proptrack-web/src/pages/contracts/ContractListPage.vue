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
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Contracts</h1>
        <p class="page-subtitle">{{ meta ? `${meta.total} contracts total` : 'Manage rental agreements' }}</p>
      </div>
      <RouterLink v-if="canCreate" to="/contracts/new" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        New contract
      </RouterLink>
    </div>

    <div class="tab-bar">
      <button v-for="tab in statusTabs" :key="tab.value"
        :class="['tab', { 'tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as ContractStatus | '')">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="contract-list">
      <div v-for="i in 5" :key="i" class="shimmer" style="height:110px;border-radius:16px;" />
    </div>

    <div v-else-if="!isLoading && contracts.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
      <p class="empty-state__title">No contracts found</p>
      <p class="empty-state__text">{{ filters.status ? `No ${filters.status} contracts.` : 'Create your first rental contract.' }}</p>
      <RouterLink v-if="canCreate && !filters.status" to="/contracts/new" class="btn-primary" style="margin-top:4px">New contract</RouterLink>
    </div>

    <div v-else class="contract-list">
      <ContractCard v-for="contract in contracts" :key="contract.id" :contract="contract" :show-actions="canCreate" @terminate="handleTerminate" @download="handleDownload" />
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
.contract-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 28px;
}
</style>
