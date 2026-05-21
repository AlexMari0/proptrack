<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { paymentService } from '@/services/paymentService'
import { invoiceService } from '@/services/invoiceService'
import { useEcho } from '@/composables/useEcho'
import PaymentStatus from '@/components/payment/PaymentStatus.vue'
import type { Invoice } from '@/types/invoice'
import type { MidtransResult } from '@/types/payment'

// ─── State ────────────────────────────────────────────────────────────────────

type PageState = 'loading' | 'invoice' | 'processing' | 'success' | 'failed' | 'error'

const route  = useRoute()
const router = useRouter()

const invoiceId   = route.params.id as string
const invoice     = ref<Invoice | null>(null)
const state       = ref<PageState>('loading')
const errorMsg    = ref('')
const snapLoaded  = ref(false)
const pollTimer   = ref<ReturnType<typeof setInterval> | null>(null)
const pollCount   = ref(0)
const MAX_POLLS   = 60 // 3 min at 3s intervals

const { getEcho } = useEcho()
let activeInvoiceChannel: string | null = null

function subscribeToInvoice(id: string) {
  const echo = getEcho()
  if (echo) {
    if (activeInvoiceChannel) {
      echo.leave(`invoice.${activeInvoiceChannel}`)
    }
    activeInvoiceChannel = id
    echo.private(`invoice.${id}`)
      .listen('.PaymentConfirmed', async () => {
        stopPolling()
        await loadInvoice()
        state.value = 'success'
      })
  }
}

function unsubscribeFromInvoice() {
  const echo = getEcho()
  if (echo && activeInvoiceChannel) {
    echo.leave(`invoice.${activeInvoiceChannel}`)
    activeInvoiceChannel = null
  }
}

// ─── Derived ──────────────────────────────────────────────────────────────────

const formattedAmount = computed(() => {
  if (!invoice.value) return ''
  return new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
  }).format(invoice.value.amount)
})

const formattedMonth = computed(() => {
  if (!invoice.value) return ''
  const [year, month] = invoice.value.billing_month.split('-')
  return new Date(Number(year), Number(month) - 1).toLocaleDateString('id-ID', {
    month: 'long', year: 'numeric',
  })
})

const formattedDueDate = computed(() => {
  if (!invoice.value) return ''
  return new Date(invoice.value.due_date).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric',
  })
})

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([loadInvoice(), loadSnap()])
  subscribeToInvoice(invoiceId)
})

onUnmounted(() => {
  stopPolling()
  unsubscribeFromInvoice()
})

// ─── Actions ──────────────────────────────────────────────────────────────────

async function loadInvoice() {
  try {
    const res = await invoiceService.get(invoiceId)
    invoice.value = res.data

    // Already paid — jump straight to success
    if (invoice.value.status === 'paid') {
      state.value = 'success'
      return
    }

    state.value = 'invoice'
  } catch {
    state.value = 'error'
    errorMsg.value = 'Invoice not found.'
  }
}

async function loadSnap() {
  try {
    await paymentService.loadSnapScript()
    snapLoaded.value = true
  } catch (e) {
    // Non-fatal — user will see "Pay Now" disabled with a tooltip
    console.warn('Midtrans Snap not loaded:', e)
  }
}

async function handlePay() {
  if (!invoice.value) return
  state.value = 'processing'

  try {
    const res = await paymentService.createTransaction({
      invoice_id: invoice.value.id,
      gateway: 'midtrans',
    })

    const token = res.data.transaction_token

    if (!window.snap) {
      // Fallback: open redirect URL directly
      window.location.href = res.data.redirect_url
      return
    }

    window.snap.pay(token, {
      onSuccess: (_result: MidtransResult) => {
        state.value = 'processing'
        startPolling()
      },
      onPending: (_result: MidtransResult) => {
        state.value = 'processing'
        startPolling()
      },
      onError: (_result: MidtransResult) => {
        state.value = 'failed'
      },
      onClose: () => {
        // User closed the popup without paying — go back to invoice view
        if (state.value === 'processing') {
          state.value = 'invoice'
        }
      },
    })
  } catch (err: unknown) {
    state.value = 'failed'
    if (err && typeof err === 'object' && 'response' in err) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      errorMsg.value = axiosErr.response?.data?.message ?? 'Payment failed.'
    } else {
      errorMsg.value = 'Payment failed.'
    }
  }
}

function startPolling() {
  pollCount.value = 0
  pollTimer.value = setInterval(async () => {
    pollCount.value++

    try {
      const res = await paymentService.getStatus(invoiceId)
      const status = res.data.status

      if (status === 'paid') {
        stopPolling()
        // Refresh invoice data to show paid_at, gateway
        await loadInvoice()
        state.value = 'success'
        return
      }

      if (status === 'cancelled') {
        stopPolling()
        state.value = 'failed'
        errorMsg.value = 'Invoice was cancelled.'
        return
      }
    } catch { /* network hiccup — keep polling */ }

    if (pollCount.value >= MAX_POLLS) {
      stopPolling()
      state.value = 'failed'
      errorMsg.value = 'Payment confirmation timed out. Please check your invoice status.'
    }
  }, 3000)
}

function stopPolling() {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}
</script>

<template>
  <div class="pay-page">
    <!-- Loading skeleton -->
    <div v-if="state === 'loading'" class="pay-page__skeleton">
      <div class="skeleton-block skeleton-block--tall" />
      <div class="skeleton-block" />
    </div>

    <!-- Error state -->
    <div v-else-if="state === 'error'" class="pay-page__feedback pay-page__feedback--error">
      <div class="feedback-icon">✕</div>
      <h2>{{ errorMsg }}</h2>
      <button class="btn btn--ghost" @click="router.push({ name: 'invoices' })">Back to Invoices</button>
    </div>

    <!-- Success state -->
    <div v-else-if="state === 'success'" class="pay-page__feedback pay-page__feedback--success">
      <div class="feedback-icon feedback-icon--success">✓</div>
      <h2>Payment Confirmed!</h2>
      <p v-if="invoice">
        <strong>{{ invoice.invoice_number }}</strong> has been marked as paid.
      </p>
      <div class="feedback-actions">
        <button class="btn btn--primary" @click="router.push({ name: 'invoice-detail', params: { id: invoiceId } })">
          View Invoice
        </button>
        <button class="btn btn--ghost" @click="router.push({ name: 'invoices' })">
          All Invoices
        </button>
      </div>
    </div>

    <!-- Failed state -->
    <div v-else-if="state === 'failed'" class="pay-page__feedback pay-page__feedback--error">
      <div class="feedback-icon">✕</div>
      <h2>Payment Failed</h2>
      <p>{{ errorMsg || 'Something went wrong. Please try again.' }}</p>
      <div class="feedback-actions">
        <button class="btn btn--primary" @click="state = 'invoice'">Try Again</button>
        <button class="btn btn--ghost" @click="router.push({ name: 'invoices' })">Back to Invoices</button>
      </div>
    </div>

    <!-- Processing / polling -->
    <div v-else-if="state === 'processing'" class="pay-page__feedback pay-page__feedback--processing">
      <div class="spinner" />
      <h2>Awaiting Payment Confirmation</h2>
      <p>We're checking with the payment gateway&hellip;</p>
      <p class="pay-page__poll-hint">This page updates automatically every 3 seconds.</p>
    </div>

    <!-- Invoice detail + Pay Now button -->
    <div v-else-if="state === 'invoice' && invoice" class="pay-page__content">
      <button class="back-btn" @click="router.back()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
        </svg>
        Back
      </button>

      <div class="pay-card">
        <!-- Card header -->
        <div class="pay-card__header">
          <div class="pay-card__brand">PropTrack Pay</div>
          <PaymentStatus :status="invoice.status" />
        </div>

        <!-- Invoice summary -->
        <div class="pay-card__summary">
          <div class="pay-card__invoice-number">{{ invoice.invoice_number }}</div>
          <div class="pay-card__property">{{ invoice.property.name }}</div>
          <div class="pay-card__period">{{ formattedMonth }}</div>
        </div>

        <hr class="pay-card__divider" />

        <!-- Details grid -->
        <div class="pay-card__details">
          <div class="pay-detail">
            <span class="pay-detail__label">Tenant</span>
            <span class="pay-detail__value">{{ invoice.tenant.name }}</span>
          </div>
          <div class="pay-detail">
            <span class="pay-detail__label">Due Date</span>
            <span class="pay-detail__value" :class="{ 'pay-detail__value--danger': invoice.status === 'overdue' }">
              {{ formattedDueDate }}
            </span>
          </div>
        </div>

        <hr class="pay-card__divider" />

        <!-- Amount -->
        <div class="pay-card__amount-row">
          <span class="pay-card__amount-label">Total Amount</span>
          <span class="pay-card__amount">{{ formattedAmount }}</span>
        </div>

        <!-- Pay button -->
        <button
          class="pay-card__cta"
          :disabled="!snapLoaded"
          :title="snapLoaded ? '' : 'Payment gateway loading…'"
          @click="handlePay"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1v6A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-6zM3 13.25a.75.75 0 01.75-.75H6a.75.75 0 010 1.5H3.75a.75.75 0 01-.75-.75zm9.75-.75a.75.75 0 000 1.5h2.5a.75.75 0 000-1.5h-2.5z" clip-rule="evenodd" />
          </svg>
          Pay Now via Midtrans
        </button>

        <p class="pay-card__secure">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
          </svg>
          Secured by Midtrans · Your payment info is never stored on our servers
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pay-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
}

/* ─── Feedback states ─────────────────────────────────────────────────────── */
.pay-page__feedback {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  max-width: 420px;
}
.pay-page__feedback h2 { font-size: 1.4rem; font-weight: 700; color: var(--color-text); margin: 0; }
.pay-page__feedback p  { font-size: 0.9rem; color: var(--color-text-muted); margin: 0; }

.feedback-icon {
  width: 64px; height: 64px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.8rem; font-weight: 700;
  background: rgba(239,68,68,0.12); color: #dc2626;
}
.feedback-icon--success { background: rgba(22,163,74,0.12); color: #16a34a; }

.feedback-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 6px; }

/* Processing spinner */
.pay-page__feedback--processing h2 { color: var(--color-text); }
.spinner {
  width: 52px; height: 52px;
  border: 4px solid var(--color-border);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.pay-page__poll-hint { font-size: 0.8rem; color: var(--color-text-muted); opacity: 0.7; }

/* ─── Skeleton ────────────────────────────────────────────────────────────── */
.pay-page__skeleton { display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: 440px; }
.skeleton-block {
  height: 60px; border-radius: 14px;
  background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%);
  background-size: 200% 100%; animation: shimmer 1.4s infinite;
}
.skeleton-block--tall { height: 380px; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* ─── Back button ─────────────────────────────────────────────────────────── */
.pay-page__content { width: 100%; max-width: 440px; display: flex; flex-direction: column; gap: 0; }
.back-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: none; border: none; color: var(--color-text-muted);
  font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 20px;
  transition: color 0.2s;
}
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

/* ─── Payment card ────────────────────────────────────────────────────────── */
.pay-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 20px;
  padding: 28px 28px 24px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.08);
}
.pay-card__header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px;
}
.pay-card__brand { font-size: 1rem; font-weight: 700; color: var(--color-primary); letter-spacing: -0.3px; }

.pay-card__summary { text-align: center; margin-bottom: 20px; }
.pay-card__invoice-number { font-family: monospace; font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 6px; }
.pay-card__property { font-size: 1.15rem; font-weight: 700; color: var(--color-text); margin-bottom: 4px; }
.pay-card__period { font-size: 0.875rem; color: var(--color-text-muted); }

.pay-card__divider { border: none; border-top: 1px solid var(--color-border); margin: 16px 0; }

.pay-card__details { display: flex; flex-direction: column; gap: 10px; margin-bottom: 4px; }
.pay-detail { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.pay-detail__label { color: var(--color-text-muted); }
.pay-detail__value { font-weight: 600; color: var(--color-text); }
.pay-detail__value--danger { color: #dc2626; }

.pay-card__amount-row { display: flex; align-items: baseline; justify-content: space-between; margin: 4px 0 20px; }
.pay-card__amount-label { font-size: 0.875rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.pay-card__amount { font-size: 1.8rem; font-weight: 700; color: var(--color-primary); }

.pay-card__cta {
  width: 100%;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px 20px; border-radius: 12px; border: none;
  background: linear-gradient(135deg, var(--color-primary), #7c3aed);
  color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
  margin-bottom: 14px;
}
.pay-card__cta:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.pay-card__cta:disabled { opacity: 0.5; cursor: not-allowed; }
.pay-card__cta svg { width: 20px; height: 20px; }

.pay-card__secure {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  font-size: 0.75rem; color: var(--color-text-muted); text-align: center; margin: 0;
}
.pay-card__secure svg { width: 13px; height: 13px; flex-shrink: 0; }

/* ─── Shared buttons ──────────────────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 20px; border-radius: 10px;
  font-size: 0.875rem; font-weight: 600; cursor: pointer;
  border: none; transition: all 0.2s;
}
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover { background: var(--color-primary-hover, #4f46e5); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
</style>
