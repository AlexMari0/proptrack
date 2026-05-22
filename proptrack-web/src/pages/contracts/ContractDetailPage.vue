<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContract } from '@/composables/useContract'
import { useAuthStore } from '@/stores/auth'
import { invoiceService } from '@/services/invoiceService'
import InvoiceCard from '@/components/payment/InvoiceCard.vue'
import type { Invoice } from '@/types/invoice'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedContract, isLoading, isSubmitting, error, fetchContract, terminateContract, downloadDocument } = useContract()

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const statusBadge = computed(() => {
  if (!selectedContract.value) return ''
  return { active: 'badge badge--green', expired: 'badge badge--gray', terminated: 'badge badge--red' }[selectedContract.value.status] ?? 'badge badge--gray'
})

const statusLabel = computed(() => {
  if (!selectedContract.value) return ''
  return { active: 'Active', expired: 'Expired', terminated: 'Terminated' }[selectedContract.value.status] ?? selectedContract.value.status
})

const formatIDR = (v: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)

const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return ''
  const normalized = dateStr.includes('-') && !dateStr.includes('T') ? dateStr.replace(/-/g, '/') : dateStr
  return new Date(normalized).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })
}

const invoices = ref<Invoice[]>([])
const isInvoicesLoading = ref(false)

async function fetchInvoices() {
  if (!selectedContract.value) return
  isInvoicesLoading.value = true
  try {
    const res = await invoiceService.list({ contract_id: selectedContract.value.id, per_page: 50 })
    invoices.value = res.data
  } catch (err) {
    console.error('Failed to fetch contract invoices:', err)
  } finally {
    isInvoicesLoading.value = false
  }
}

onMounted(async () => {
  await fetchContract(route.params.id as string)
  await fetchInvoices()
})

async function handleTerminate() {
  if (!confirm('Terminate this contract? This cannot be undone.')) return
  const ok = await terminateContract(route.params.id as string)
  if (ok) {
    await fetchContract(route.params.id as string)
    await fetchInvoices()
  }
}

async function handleDownload() {
  await downloadDocument(route.params.id as string)
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
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back to contracts
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="sk-wrap">
      <div class="shimmer" style="height:80px;border-radius:14px;margin-bottom:20px" />
      <div class="sk-grid">
        <div v-for="i in 6" :key="i" class="shimmer" style="height:80px;border-radius:12px" />
      </div>
    </div>

    <div v-else-if="selectedContract">
      <div class="page-header">
        <div>
          <div style="margin-bottom:8px">
            <span :class="statusBadge">{{ statusLabel }}</span>
          </div>
          <h1 class="page-title">
            {{ selectedContract.tenant.name }} → {{ selectedContract.property.name }}
          </h1>
        </div>
        <div v-if="canManage" style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn-ghost" :disabled="isSubmitting" @click="handleDownload">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Download PDF
          </button>
          <RouterLink v-if="selectedContract.status === 'active'" :to="{ name: 'contract-edit', params: { id: selectedContract.id } }" class="btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </RouterLink>
          <button v-if="selectedContract.status === 'active'" class="btn-danger" :disabled="isSubmitting" @click="handleTerminate">
            Terminate
          </button>
        </div>
      </div>

      <div class="card">
        <p class="section-label">Contract details</p>
        <div class="info-grid" style="margin-top:12px">
          <div class="info-item">
            <span class="info-item__label">Tenant</span>
            <RouterLink class="info-link" :to="{ name: 'tenant-detail', params: { id: selectedContract.tenant.id } }">
              {{ selectedContract.tenant.name }}
            </RouterLink>
          </div>
          <div class="info-item">
            <span class="info-item__label">Property</span>
            <RouterLink class="info-link" :to="{ name: 'property-detail', params: { id: selectedContract.property.id } }">
              {{ selectedContract.property.name }}
            </RouterLink>
          </div>
          <div class="info-item">
            <span class="info-item__label">Monthly rent</span>
            <span class="tabular-nums" style="font-size:1.1rem;font-weight:700;color:var(--amber)">{{ formatIDR(selectedContract.monthly_rent) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Deposit</span>
            <span class="tabular-nums" style="font-weight:600;color:var(--g700)">{{ formatIDR(selectedContract.deposit_amount) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Contract period</span>
            <span style="font-size:0.875rem;color:var(--g700)">{{ formatDate(selectedContract.start_date) }} → {{ formatDate(selectedContract.end_date) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Billing date</span>
            <span style="font-size:0.875rem;color:var(--g700)">Day {{ selectedContract.billing_date }} of each month</span>
          </div>
          <div v-if="selectedContract.terminated_at" class="info-item" style="background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2);border-radius:10px;padding:12px">
            <span class="info-item__label" style="color:#dc2626">Terminated on</span>
            <span style="font-size:0.875rem;color:#dc2626;font-weight:600">{{ formatDate(selectedContract.terminated_at) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Created</span>
            <span style="font-size:0.875rem;color:var(--g500)">{{ formatDate(selectedContract.created_at) }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Contract ID</span>
            <span style="font-family:monospace;font-size:0.75rem;color:var(--g400)">{{ selectedContract.id }}</span>
          </div>
        </div>
      </div>

      <!-- Invoice History -->
      <section class="card" style="margin-top:20px">
        <p class="section-label">Invoice History</p>
        <div v-if="isInvoicesLoading" class="invoice-list">
          <div v-for="i in 3" :key="i" class="shimmer" style="height:96px;border-radius:16px;" />
        </div>
        <div v-else-if="invoices.length === 0" class="no-invoices">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;color:var(--g300)" aria-hidden="true">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
            <path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/>
          </svg>
          <p style="margin:0;font-size:0.875rem;color:var(--g500)">No invoices found for this contract</p>
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
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}
.info-item { display: flex; flex-direction: column; gap: 4px; }
.info-item__label { font-size: 0.7rem; font-weight: 600; color: var(--g400); text-transform: uppercase; letter-spacing: 0.04em; }
.info-link { font-size: 0.875rem; font-weight: 600; color: var(--amber); text-decoration: none; }
.info-link:hover { text-decoration: underline; }

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
