import { defineStore } from 'pinia'
import type { Invoice, InvoiceMeta, InvoiceFilters } from '@/types/invoice'

export const useInvoiceStore = defineStore('invoice', {
  state: () => ({
    invoices: [] as Invoice[],
    meta: null as InvoiceMeta | null,
    filters: {
      status: '',
      property_id: '',
      tenant_id: '',
      month: '',
      page: 1,
      per_page: 15,
    } as InvoiceFilters,

    selectedInvoice: null as Invoice | null,

    isLoading: false,
    isSubmitting: false,
    error: null as string | null,
  }),

  getters: {
    hasInvoices: (state): boolean => state.invoices.length > 0,
  },

  actions: {
    setInvoices(invoices: Invoice[], meta: InvoiceMeta) {
      this.invoices = invoices
      this.meta = meta
    },

    setSelectedInvoice(invoice: Invoice | null) {
      this.selectedInvoice = invoice
    },

    updateInvoice(updated: Invoice) {
      const idx = this.invoices.findIndex((i) => i.id === updated.id)
      if (idx !== -1) this.invoices[idx] = updated
      if (this.selectedInvoice?.id === updated.id) this.selectedInvoice = updated
    },

    setFilters(filters: Partial<InvoiceFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    resetFilters() {
      this.filters = { status: '', property_id: '', tenant_id: '', month: '', page: 1, per_page: 15 }
    },

    setLoading(v: boolean)    { this.isLoading = v },
    setSubmitting(v: boolean) { this.isSubmitting = v },
    setError(msg: string | null) { this.error = msg },
  },
})
