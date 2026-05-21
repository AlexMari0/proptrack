<script setup lang="ts">
import type { InvoiceStatus } from '@/types/invoice'

const props = defineProps<{
  status: InvoiceStatus
  size?: 'sm' | 'md'
}>()

const config: Record<InvoiceStatus, { label: string; cls: string }> = {
  unpaid:    { label: 'Unpaid',    cls: 'status--unpaid' },
  paid:      { label: 'Paid',      cls: 'status--paid' },
  overdue:   { label: 'Overdue',   cls: 'status--overdue' },
  cancelled: { label: 'Cancelled', cls: 'status--cancelled' },
}
</script>

<template>
  <span :class="['payment-status', config[status].cls, size === 'sm' ? 'payment-status--sm' : '']">
    <span class="payment-status__dot" />
    {{ config[status].label }}
  </span>
</template>

<style scoped>
.payment-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.payment-status--sm {
  font-size: 0.65rem;
  padding: 3px 8px;
}
.payment-status__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Status themes */
.status--unpaid    { background: rgba(251,191,36,0.15); color: #d97706; }
.status--unpaid    .payment-status__dot { background: #d97706; }

.status--paid      { background: rgba(34,197,94,0.15);  color: #16a34a; }
.status--paid      .payment-status__dot { background: #16a34a; }

.status--overdue   { background: rgba(239,68,68,0.12);  color: #dc2626; }
.status--overdue   .payment-status__dot { background: #dc2626; animation: pulse 1.5s infinite; }

.status--cancelled { background: var(--color-surface-alt); color: var(--color-text-muted); border: 1px solid var(--color-border); }
.status--cancelled .payment-status__dot { background: var(--color-text-muted); }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.3; }
}
</style>
