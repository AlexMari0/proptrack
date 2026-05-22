<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInvoice } from '@/composables/useInvoice'
import { useAuthStore } from '@/stores/auth'
import PaymentStatus from '@/components/payment/PaymentStatus.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedInvoice, isLoading, isSubmitting, error, fetchInvoice, sendNotification, downloadDocument } = useInvoice()

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const formattedAmount = computed(() => {
  if (!selectedInvoice.value) return ''
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(selectedInvoice.value.amount)
})

const formattedMonth = computed(() => {
  if (!selectedInvoice.value) return ''
  const [year, month] = selectedInvoice.value.billing_month.split('-')
  return new Date(Number(year), Number(month) - 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
})

const isActionable = computed(() =>
  selectedInvoice.value && ['unpaid', 'overdue'].includes(selectedInvoice.value.status),
)

onMounted(() => fetchInvoice(route.params.id as string))

async function handleSend() {
  if (!confirm('Send payment reminder to tenant?')) return
  await sendNotification(route.params.id as string)
}

async function handleDownload() {
  if (!selectedInvoice.value) return
  await downloadDocument(selectedInvoice.value.id, selectedInvoice.value.invoice_number)
}
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back to invoices
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="sk-wrap">
      <div class="shimmer" style="height:80px;border-radius:14px;margin-bottom:20px" />
      <div class="sk-grid">
        <div v-for="i in 6" :key="i" class="shimmer" style="height:80px;border-radius:12px" />
      </div>
    </div>

    <div v-else-if="selectedInvoice">
      <div class="page-header">
        <div>
          <div style="margin-bottom:8px">
            <PaymentStatus :status="selectedInvoice.status" />
          </div>
          <h1 class="page-title" style="font-family:monospace;letter-spacing:0.02em">{{ selectedInvoice.invoice_number }}</h1>
          <p class="page-subtitle">{{ formattedMonth }} · {{ selectedInvoice.tenant.name }} → {{ selectedInvoice.property.name }}</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn-ghost" :disabled="isSubmitting" @click="handleDownload">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Download PDF
          </button>
          <button v-if="canManage && isActionable" class="btn-ghost" :disabled="isSubmitting" @click="handleSend">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="22" x2="11" y1="2" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send reminder
          </button>
          <RouterLink v-if="isActionable" class="btn-primary" :to="{ name: 'payment', params: { id: selectedInvoice.id } }">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
            Pay now
          </RouterLink>
        </div>
      </div>

      <!-- Amount hero -->
      <div class="amount-hero">
        <span style="font-size:0.72rem;font-weight:600;color:var(--g500);text-transform:uppercase;letter-spacing:0.06em">Amount due</span>
        <span class="amount-hero__value tabular-nums">{{ formattedAmount }}</span>
      </div>

      <div class="card">
        <p class="section-label">Invoice details</p>
        <div class="info-grid" style="margin-top:12px">
          <div class="info-item">
            <span class="info-item__label">Invoice number</span>
            <span style="font-family:monospace;font-size:0.875rem;color:var(--g700)">{{ selectedInvoice.invoice_number }}</span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Billing period</span>
            <span>{{ formattedMonth }}</span>
          </div>
          <div class="info-item" :style="selectedInvoice.status === 'overdue' ? 'background:rgba(239,68,68,0.04);border-color:rgba(239,68,68,0.2);border-radius:8px;padding:8px' : ''">
            <span class="info-item__label">Due date</span>
            <span :style="selectedInvoice.status === 'overdue' ? 'color:#dc2626;font-weight:600' : ''">
              {{ new Date(selectedInvoice.due_date).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}
            </span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Tenant</span>
            <RouterLink class="info-link" :to="{ name: 'tenant-detail', params: { id: selectedInvoice.tenant.id } }">{{ selectedInvoice.tenant.name }}</RouterLink>
          </div>
          <div class="info-item">
            <span class="info-item__label">Property</span>
            <RouterLink class="info-link" :to="{ name: 'property-detail', params: { id: selectedInvoice.property.id } }">{{ selectedInvoice.property.name }}</RouterLink>
          </div>
          <div class="info-item">
            <span class="info-item__label">Contract</span>
            <RouterLink class="info-link" :to="{ name: 'contract-detail', params: { id: selectedInvoice.contract.id } }">View contract</RouterLink>
          </div>
          <div v-if="selectedInvoice.paid_at" class="info-item" style="background:rgba(22,163,74,0.04);border-color:rgba(22,163,74,0.2);border-radius:8px;padding:8px">
            <span class="info-item__label" style="color:#16a34a">Paid on</span>
            <span style="font-weight:600;color:#16a34a;display:flex;align-items:center;gap:6px">
              {{ new Date(selectedInvoice.paid_at).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}
              <span v-if="selectedInvoice.payment_gateway" style="font-size:0.68rem;background:rgba(22,163,74,0.12);padding:2px 7px;border-radius:8px;text-transform:uppercase">{{ selectedInvoice.payment_gateway }}</span>
            </span>
          </div>
          <div class="info-item">
            <span class="info-item__label">Created</span>
            <span style="color:var(--g400);font-size:0.8rem">{{ new Date(selectedInvoice.created_at).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sk-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.amount-hero {
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 14px;
  padding: 20px 24px;
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.amount-hero__value {
  font-size: 2.2rem;
  font-weight: 800;
  color: var(--g900);
  letter-spacing: -0.04em;
  line-height: 1.1;
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
</style>
