<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import PaymentStatus from '@/components/payment/PaymentStatus.vue'
import type { Invoice } from '@/types/invoice'

const props = defineProps<{
  invoice: Invoice
  showActions?: boolean
}>()

const emit = defineEmits<{
  send: [id: string]
  download: [id: string, invoiceNumber: string]
}>()

const router = useRouter()

const formattedAmount = computed(() =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
  }).format(props.invoice.amount),
)

const formattedMonth = computed(() => {
  const [year, month] = props.invoice.billing_month.split('-')
  const date = new Date(Number(year), Number(month) - 1)
  return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
})

const formattedDueDate = computed(() =>
  new Date(props.invoice.due_date).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric',
  }),
)

const isActionable = computed(() =>
  props.showActions && ['unpaid', 'overdue'].includes(props.invoice.status),
)

function goToDetail() {
  router.push({ name: 'invoice-detail', params: { id: props.invoice.id } })
}
</script>

<template>
  <article class="invoice-card" @click="goToDetail">
    <div class="invoice-card__header">
      <div class="invoice-card__left">
        <span class="invoice-card__number">{{ invoice.invoice_number }}</span>
        <PaymentStatus :status="invoice.status" size="sm" />
      </div>
      <span class="invoice-card__amount">{{ formattedAmount }}</span>
    </div>

    <div class="invoice-card__body">
      <div class="invoice-card__parties">
        <span class="invoice-card__tenant">{{ invoice.tenant.name }}</span>
        <span class="invoice-card__sep">·</span>
        <span class="invoice-card__property">{{ invoice.property.name }}</span>
      </div>
      <div class="invoice-card__dates">
        <span class="invoice-card__month">{{ formattedMonth }}</span>
        <span class="invoice-card__sep">·</span>
        <span :class="['invoice-card__due', { 'invoice-card__due--overdue': invoice.status === 'overdue' }]">
          Due {{ formattedDueDate }}
        </span>
      </div>
    </div>

    <div v-if="isActionable || showActions" class="invoice-card__actions" @click.stop>
      <button
        class="invoice-card__btn"
        @click.stop="emit('download', invoice.id, invoice.invoice_number)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L7.29 9.22a.75.75 0 00-1.08 1.04l3.25 3.5a.75.75 0 001.08 0l3.25-3.5a.75.75 0 10-1.08-1.04l-1.96 2.144V2.75z" />
          <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
        </svg>
        PDF
      </button>
      <button
        v-if="isActionable"
        class="invoice-card__btn invoice-card__btn--send"
        @click.stop="emit('send', invoice.id)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z" />
        </svg>
        Send
      </button>
    </div>
  </article>
</template>

<style scoped>
.invoice-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  padding: 16px 20px;
  cursor: pointer;
  transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}
.invoice-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0,0,0,0.1);
  border-color: var(--color-primary);
}

.invoice-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}
.invoice-card__left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.invoice-card__number {
  font-family: monospace;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-text);
  letter-spacing: 0.02em;
}
.invoice-card__amount {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-primary);
}

.invoice-card__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 0;
}
.invoice-card__parties, .invoice-card__dates {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: var(--color-text-muted);
}
.invoice-card__tenant { font-weight: 600; color: var(--color-text); }
.invoice-card__sep { opacity: 0.4; }
.invoice-card__due--overdue { color: #dc2626; font-weight: 600; }

.invoice-card__actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.invoice-card__btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-muted);
  transition: all 0.2s;
}
.invoice-card__btn svg { width: 14px; height: 14px; }
.invoice-card__btn:hover { border-color: var(--color-primary); color: var(--color-primary); background: rgba(99,102,241,0.07); }
.invoice-card__btn--send:hover { border-color: #16a34a; color: #16a34a; background: rgba(22,163,74,0.08); }
</style>
