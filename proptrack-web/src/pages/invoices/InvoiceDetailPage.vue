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
  <div class="page">
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back to Invoices
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Skeleton -->
    <div v-if="isLoading" class="skeleton-wrap">
      <div class="skeleton-header" />
      <div class="skeleton-grid">
        <div v-for="i in 6" :key="i" class="skeleton-card" />
      </div>
    </div>

    <div v-else-if="selectedInvoice" class="detail">
      <!-- Header -->
      <div class="detail__header">
        <div>
          <div class="detail__badges">
            <PaymentStatus :status="selectedInvoice.status" />
          </div>
          <h1 class="detail__title">{{ selectedInvoice.invoice_number }}</h1>
          <p class="detail__sub">{{ formattedMonth }} · {{ selectedInvoice.tenant.name }} → {{ selectedInvoice.property.name }}</p>
        </div>

        <div v-if="canManage" class="detail__actions">
          <button class="btn btn--ghost" :disabled="isSubmitting" @click="handleDownload">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L7.29 9.22a.75.75 0 00-1.08 1.04l3.25 3.5a.75.75 0 001.08 0l3.25-3.5a.75.75 0 10-1.08-1.04l-1.96 2.144V2.75z" />
              <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
            </svg>
            Download PDF
          </button>
          <button v-if="isActionable" class="btn btn--primary" :disabled="isSubmitting" @click="handleSend">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z" />
            </svg>
            Send Reminder
          </button>
          <RouterLink
            v-if="isActionable"
            class="btn btn--accent"
            :to="{ name: 'payment', params: { id: selectedInvoice.id } }"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1v6A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-6z" clip-rule="evenodd" />
            </svg>
            Pay Now
          </RouterLink>
        </div>
      </div>

      <!-- Info grid -->
      <div class="info-grid">
        <div class="info-card">
          <span class="info-card__label">Invoice Number</span>
          <span class="info-card__value mono">{{ selectedInvoice.invoice_number }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Billing Period</span>
          <span class="info-card__value">{{ formattedMonth }}</span>
        </div>
        <div class="info-card info-card--amount">
          <span class="info-card__label">Amount Due</span>
          <span class="info-card__value info-card__value--price">{{ formattedAmount }}</span>
        </div>
        <div class="info-card" :class="{ 'info-card--danger': selectedInvoice.status === 'overdue' }">
          <span class="info-card__label">Due Date</span>
          <span class="info-card__value">
            {{ new Date(selectedInvoice.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
          </span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Tenant</span>
          <RouterLink class="info-card__link" :to="{ name: 'tenant-detail', params: { id: selectedInvoice.tenant.id } }">
            {{ selectedInvoice.tenant.name }}
          </RouterLink>
        </div>
        <div class="info-card">
          <span class="info-card__label">Property</span>
          <RouterLink class="info-card__link" :to="{ name: 'property-detail', params: { id: selectedInvoice.property.id } }">
            {{ selectedInvoice.property.name }}
          </RouterLink>
        </div>
        <div class="info-card">
          <span class="info-card__label">Contract</span>
          <RouterLink class="info-card__link" :to="{ name: 'contract-detail', params: { id: selectedInvoice.contract.id } }">
            View Contract
          </RouterLink>
        </div>
        <div v-if="selectedInvoice.paid_at" class="info-card info-card--success">
          <span class="info-card__label">Paid On</span>
          <span class="info-card__value">
            {{ new Date(selectedInvoice.paid_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
            <span v-if="selectedInvoice.payment_gateway" class="tag">{{ selectedInvoice.payment_gateway }}</span>
          </span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Created</span>
          <span class="info-card__value">
            {{ new Date(selectedInvoice.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 900px; margin: 0 auto; }
.back-btn { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; color: var(--color-text-muted); font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 24px; transition: color 0.2s; }
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

.detail__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.detail__badges { margin-bottom: 10px; }
.detail__title { font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin: 0 0 6px; font-family: monospace; }
.detail__sub { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }
.detail__actions { display: flex; gap: 10px; flex-wrap: wrap; }

.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.info-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
.info-card--amount { border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.04); }
.info-card--danger { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.04); }
.info-card--success { border-color: rgba(22,163,74,0.3); background: rgba(22,163,74,0.04); }
.info-card__label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.info-card__value { font-size: 0.9rem; color: var(--color-text); font-weight: 500; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.info-card__value--price { font-size: 1.3rem; font-weight: 700; color: var(--color-primary); }
.info-card__link { font-size: 0.9rem; font-weight: 600; color: var(--color-primary); text-decoration: none; }
.info-card__link:hover { text-decoration: underline; }
.mono { font-family: monospace; }
.tag { font-size: 0.72rem; background: rgba(22,163,74,0.12); color: #16a34a; padding: 2px 8px; border-radius: 10px; font-weight: 600; text-transform: uppercase; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 24px; }
.skeleton-header { height: 80px; border-radius: 14px; }
.skeleton-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.skeleton-card { height: 80px; border-radius: 12px; }
.skeleton-header, .skeleton-card { background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn svg { width: 16px; height: 16px; }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.btn--accent { background: linear-gradient(135deg, #16a34a, #059669); color: #fff; }
.btn--accent:hover { opacity: 0.9; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
