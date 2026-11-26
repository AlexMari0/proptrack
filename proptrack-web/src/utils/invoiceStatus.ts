import type { InvoiceStatus } from '@/types/invoice'

/**
 * Presentation of an Invoice's effective status (see GLOSSARY.md, Billing).
 *
 * The API decides the status, including whether an invoice is overdue, and
 * whether it can be paid (`is_payable`) or is still owed (`is_outstanding`).
 * The frontend never works these out itself. It only labels and colours them here.
 */

export type InvoiceStatusTone = 'positive' | 'negative' | 'warning' | 'neutral'

const STATUS_META: Record<InvoiceStatus, { label: string; tone: InvoiceStatusTone }> = {
  unpaid:    { label: 'Unpaid',    tone: 'warning' },
  overdue:   { label: 'Overdue',   tone: 'negative' },
  paid:      { label: 'Paid',      tone: 'positive' },
  cancelled: { label: 'Cancelled', tone: 'neutral' },
}

/** Every effective status, in display order (e.g. filter tabs). */
export const INVOICE_STATUSES = Object.keys(STATUS_META) as InvoiceStatus[]

export const invoiceStatusLabel = (status: InvoiceStatus): string => STATUS_META[status].label

export const invoiceStatusTone = (status: InvoiceStatus): InvoiceStatusTone => STATUS_META[status].tone

export const isOverdue = (status: InvoiceStatus): boolean => status === 'overdue'

export const isPaid = (status: InvoiceStatus): boolean => status === 'paid'

export const isCancelled = (status: InvoiceStatus): boolean => status === 'cancelled'
