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
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Invoices</h1>
        <p class="page-subtitle">{{ meta ? `${meta.total} invoices total` : 'Monthly billing & payment tracking' }}</p>
      </div>
    </div>

    <div class="tab-bar">
      <button v-for="tab in statusTabs" :key="tab.value"
        :class="['tab', { 'tab--active': filters.status === tab.value }]"
        @click="onStatusChange(tab.value as InvoiceStatus | '')">
        {{ tab.label }}
      </button>
    </div>

    <div class="filter-bar" style="margin-bottom:20px">
      <select class="form-select" style="width:auto;min-width:150px" :value="filters.property_id" @change="onPropertyChange(($event.target as HTMLSelectElement).value)">
        <option value="">All properties</option>
        <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input type="month" class="form-input" style="width:auto" :value="filters.month" @change="onMonthChange(($event.target as HTMLInputElement).value)" />
      <button v-if="filters.month || filters.property_id || filters.status" class="btn-ghost" @click="applyFilters({ status: '', property_id: '', month: '' })">
        Clear filters
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="invoice-list">
      <div v-for="i in 6" :key="i" class="shimmer" style="height:96px;border-radius:16px;" />
    </div>

    <div v-else-if="!isLoading && invoices.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
        <path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/>
      </svg>
      <p class="empty-state__title">No invoices found</p>
      <p class="empty-state__text">Invoices are auto-generated for active contracts each month.</p>
    </div>

    <div v-else class="invoice-list">
      <InvoiceCard v-for="invoice in invoices" :key="invoice.id" :invoice="invoice" :show-actions="canManage" @send="handleSend" @download="handleDownload" />
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
.invoice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 28px;
}
</style>
