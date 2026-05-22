<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import { useAuthStore } from '@/stores/auth'
import { invoiceService } from '@/services/invoiceService'
import InvoiceCard from '@/components/payment/InvoiceCard.vue'
import type { Invoice } from '@/types/invoice'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedTenant, isLoading, isSubmitting, error, fetchTenant, deleteTenant } = useTenant()

const tenantId = computed(() => route.params.id as string)

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const canDelete = computed(() =>
  authStore.user?.roles?.includes('admin') ?? false,
)

const invoices = ref<Invoice[]>([])
const isInvoicesLoading = ref(false)

onMounted(async () => {
  await fetchTenant(tenantId.value)
  await fetchInvoices()
})

async function fetchInvoices() {
  isInvoicesLoading.value = true
  try {
    const res = await invoiceService.list({ tenant_id: tenantId.value, per_page: 20 })
    invoices.value = res.data
  } catch (err) {
    console.error('Failed to fetch tenant invoices:', err)
  } finally {
    isInvoicesLoading.value = false
  }
}

async function handleSendInvoice(id: string) {
  if (!confirm('Send payment reminder notification to tenant?')) return
  try {
    await invoiceService.send(id)
    alert('Payment reminder sent successfully!')
    await fetchInvoices()
  } catch (err) {
    console.error('Failed to send invoice reminder:', err)
    alert('Failed to send payment reminder.')
  }
}

async function handleDownloadInvoice(id: string, invoiceNumber: string) {
  try {
    await invoiceService.downloadDocument(id, invoiceNumber)
  } catch (err) {
    console.error('Failed to download invoice PDF:', err)
    alert('Failed to download invoice PDF.')
  }
}

async function handleDelete() {
  if (!confirm(`Delete tenant "${selectedTenant.value?.name}"? This cannot be undone.`)) return
  await deleteTenant(tenantId.value)
}

const formatIDR = (v: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)

function formatKtp(ktp: string): string {
  if (!ktp || ktp.length !== 16) return ktp
  return `${ktp.slice(0, 4)} **** **** ${ktp.slice(12)}`
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back to tenants
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Skeleton -->
    <div v-if="isLoading" class="sk-wrap">
      <div class="shimmer" style="height:80px;border-radius:14px;margin-bottom:20px" />
      <div class="sk-grid">
        <div v-for="i in 5" :key="i" class="shimmer" style="height:72px;border-radius:12px" />
      </div>
    </div>

    <div v-else-if="selectedTenant">
      <!-- Header -->
      <div class="page-header">
        <div class="tenant-identity">
          <div class="tenant-big-avatar" aria-hidden="true">{{ selectedTenant.name.charAt(0).toUpperCase() }}</div>
          <div>
            <h1 class="page-title">{{ selectedTenant.name }}</h1>
            <p class="page-subtitle">{{ selectedTenant.email }}</p>
          </div>
        </div>
        <div v-if="canManage" style="display:flex;gap:10px">
          <RouterLink :to="{ name: 'tenant-edit', params: { id: selectedTenant.id } }" class="btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </RouterLink>
          <button v-if="canDelete" class="btn-danger" :disabled="isSubmitting" @click="handleDelete">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Info grid -->
      <section class="card" style="margin-bottom:20px">
        <p class="section-label">Contact information</p>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-item__label">Phone</span>
            <span class="info-item__value">{{ selectedTenant.phone }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">KTP number</span>
            <span class="info-item__value" style="font-family:monospace;letter-spacing:0.05em">{{ formatKtp(selectedTenant.id_card_number) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Emergency contact</span>
            <span class="info-item__value">{{ selectedTenant.emergency_contact_name || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Emergency phone</span>
            <span class="info-item__value">{{ selectedTenant.emergency_contact_phone || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Registered</span>
            <span class="info-item__value">{{ formatDate(selectedTenant.created_at) }}</span>
          </div>
        </div>
      </section>

      <!-- Active Contract -->
      <section class="card" style="margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
          <p class="section-label" style="margin:0">Active contract</p>
          <span v-if="selectedTenant.active_contract" class="badge badge--green">Active</span>
        </div>
        <div v-if="selectedTenant.active_contract" class="contract-summary" style="margin-top:0">
          <div class="contract-row">
            <span style="color:var(--g500);font-size:0.8rem">Property</span>
            <span style="font-weight:600;color:var(--g900)">{{ selectedTenant.active_contract.property.name }}</span>
          </div>
          <div class="contract-row">
            <span style="color:var(--g500);font-size:0.8rem">Period</span>
            <span>{{ formatDate(selectedTenant.active_contract.start_date) }} → {{ formatDate(selectedTenant.active_contract.end_date) }}</span>
          </div>
          <div class="contract-row">
            <span style="color:var(--g500);font-size:0.8rem">Monthly rent</span>
            <span class="tabular-nums" style="font-weight:700;color:var(--amber)">{{ formatIDR(selectedTenant.active_contract.monthly_rent) }}</span>
          </div>
        </div>
        <div v-else class="no-contract" style="margin-top:0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;color:var(--g300)" aria-hidden="true">
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <p style="margin:0;font-size:0.875rem;color:var(--g500)">No active contract</p>
        </div>
      </section>

      <!-- Invoices / Payment History -->
      <section class="card">
        <p class="section-label">Invoices & Payment History</p>
        <div v-if="isInvoicesLoading" class="invoice-list">
          <div v-for="i in 3" :key="i" class="shimmer" style="height:96px;border-radius:16px;" />
        </div>
        <div v-else-if="invoices.length === 0" class="no-invoices">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;color:var(--g300)" aria-hidden="true">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
            <path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/>
          </svg>
          <p style="margin:0;font-size:0.875rem;color:var(--g500)">No invoices found for this tenant</p>
        </div>
        <div v-else class="invoice-list">
          <InvoiceCard v-for="invoice in invoices" :key="invoice.id" :invoice="invoice" :show-actions="canManage" @send="handleSendInvoice" @download="handleDownloadInvoice" />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.sk-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}

.tenant-identity {
  display: flex;
  align-items: center;
  gap: 16px;
}

.tenant-big-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--g900);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  font-weight: 800;
  flex-shrink: 0;
  letter-spacing: -0.02em;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
  margin-top: 12px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.info-item__label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--g400);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.info-item__value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--g700);
}

.contract-summary {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 12px;
}

.contract-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--g100);
}

.contract-row:last-of-type { border-bottom: none; }

.no-contract {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 32px 24px;
  background: var(--g50);
  border: 1px dashed var(--g200);
  border-radius: 10px;
  text-align: center;
  margin-top: 12px;
}

.invoice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 12px;
}

.no-invoices {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 32px 24px;
  background: var(--g50);
  border: 1px dashed var(--g200);
  border-radius: 10px;
  text-align: center;
  margin-top: 12px;
}
</style>
