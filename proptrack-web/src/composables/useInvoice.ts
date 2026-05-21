import { computed } from 'vue'
import { useInvoiceStore } from '@/stores/invoice'
import { invoiceService } from '@/services/invoiceService'
import type { InvoiceFilters } from '@/types/invoice'

export function useInvoice() {
  const store = useInvoiceStore()

  const invoices    = computed(() => store.invoices)
  const selectedInvoice = computed(() => store.selectedInvoice)
  const meta        = computed(() => store.meta)
  const filters     = computed(() => store.filters)
  const isLoading   = computed(() => store.isLoading)
  const isSubmitting = computed(() => store.isSubmitting)
  const error       = computed(() => store.error)

  async function fetchInvoices(overrides: Partial<InvoiceFilters> = {}): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const active = { ...store.filters, ...overrides }
      const response = await invoiceService.list(active)
      store.setInvoices(response.data, response.meta)
    } catch {
      store.setError('Failed to load invoices.')
    } finally {
      store.setLoading(false)
    }
  }

  async function fetchInvoice(id: string): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    store.setSelectedInvoice(null)
    try {
      const response = await invoiceService.get(id)
      store.setSelectedInvoice(response.data)
    } catch {
      store.setError('Invoice not found.')
    } finally {
      store.setLoading(false)
    }
  }

  async function sendNotification(id: string): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await invoiceService.send(id)
      store.updateInvoice(response.data)
      return true
    } catch {
      store.setError('Failed to send notification.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function downloadDocument(id: string, invoiceNumber: string): Promise<void> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      await invoiceService.downloadDocument(id, invoiceNumber)
    } catch {
      store.setError('Failed to download invoice PDF.')
    } finally {
      store.setSubmitting(false)
    }
  }

  async function applyFilters(updates: Partial<InvoiceFilters>): Promise<void> {
    store.setFilters({ ...updates, page: 1 })
    await fetchInvoices()
  }

  async function changePage(page: number): Promise<void> {
    store.setFilters({ page })
    await fetchInvoices()
  }

  function resetFilters(): void {
    store.resetFilters()
  }

  return {
    invoices, selectedInvoice, meta, filters, isLoading, isSubmitting, error,
    fetchInvoices, fetchInvoice, sendNotification, downloadDocument,
    applyFilters, changePage, resetFilters,
  }
}
