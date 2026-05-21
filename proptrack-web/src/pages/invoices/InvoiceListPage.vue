<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useInvoice } from '@/composables/useInvoice'
import { useAuthStore } from '@/stores/auth'
import { propertyService } from '@/services/propertyService'
import InvoiceCard from '@/components/payment/InvoiceCard.vue'
import type { InvoiceStatus } from '@/types/invoice'
import type { Property } from '@/types/property'

const authStore = useAuthStore()
const {
  invoices, meta, filters, isLoading, error,
  fetchInvoices, applyFilters, changePage, sendNotification, downloadDocument,
} = useInvoice()

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

// Property dropdown for filter
const properties = ref<Property[]>([])
onMounted(async () => {
  await fetchInvoices()
  try {
    const res = await propertyService.list({ per_page: 100 })
    properties.value = res.data
  } catch { /* non-fatal */ }
})

const statusTabs = [
  { label: 'All',       value: '' },
  { label: 'Unpaid',    value: 'unpaid' },
  { label: 'Overdue',   value: 'overdue' },
  { label: 'Paid',      value: 'paid' },
  { label: 'Cancelled', value: 'cancelled' },
] as const


function onStatusChange(status: InvoiceStatus | '') {
  applyFilters({ status })
}

function onPropertyChange(propertyId: string) {
  applyFilters({ property_id: propertyId })
}

function onMonthChange(month: string) {
  applyFilters({ month })
}

async function handleSend(id: string) {
  if (!confirm('Send payment reminder notification to tenant?')) return
  await sendNotification(id)
}

async function handleDownload(id: string, invoiceNumber: string) {
  await downloadDocument(id, invoiceNumber)
}
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page__header">
      <div>
        <h1 class="page__title">Invoices</h1>
        <p class="page__subtitle">
          {{ meta ? `${meta.total} invoices total` : 'Monthly billing & payment tracking' }}
        </p>
      </div>
    </div>

    <!-- Status tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        :class="['status-tab', { 'status-tab--active': filters.status === tab.value }]"
        @click="onStatusChange(tab.value as InvoiceStatus | '')"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Filters row -->
    <div class="filters">
      <select class="filter-select" :value="filters.property_id" @change="onPropertyChange(($event.target as HTMLSelectElement).value)">
        <option value="">All properties</option>
        <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input
        type="month"
        class="filter-input"
        :value="filters.month"
        @change="onMonthChange(($event.target as HTMLInputElement).value)"
      />
      <button v-if="filters.month || filters.property_id || filters.status" class="filter-clear" @click="applyFilters({ status: '', property_id: '', month: '' })">
        Clear filters
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Skeletons -->
    <div v-if="isLoading" class="skeleton-list">
      <div v-for="i in 6" :key="i" class="skeleton-item" />
    </div>

    <!-- Empty -->
    <div v-else-if="!isLoading && invoices.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" />
      </svg>
      <h3>No invoices found</h3>
      <p>Invoices are auto-generated for active contracts each month.</p>
    </div>

    <!-- List -->
    <div v-else class="invoice-list">
      <InvoiceCard
        v-for="invoice in invoices"
        :key="invoice.id"
        :invoice="invoice"
        :show-actions="canManage"
        @send="handleSend"
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

.status-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.status-tab { padding: 6px 16px; border-radius: 20px; border: 1px solid var(--color-border); background: transparent; color: var(--color-text-muted); font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.status-tab:hover { border-color: var(--color-primary); color: var(--color-primary); }
.status-tab--active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

.filters { display: flex; gap: 10px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; }
.filter-select, .filter-input { padding: 8px 12px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface-alt); color: var(--color-text); font-size: 0.875rem; font-family: inherit; outline: none; transition: border-color 0.2s; }
.filter-select:focus, .filter-input:focus { border-color: var(--color-primary); }
.filter-clear { padding: 8px 14px; border-radius: 10px; border: 1px solid var(--color-border); background: transparent; color: var(--color-text-muted); font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
.filter-clear:hover { border-color: var(--color-primary); color: var(--color-primary); }

.invoice-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; }

.skeleton-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; }
.skeleton-item { height: 96px; border-radius: 14px; background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
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

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
