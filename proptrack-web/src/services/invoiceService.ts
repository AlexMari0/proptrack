import api from '@/plugins/axios'
import type { InvoiceFilters, InvoiceListResponse, InvoiceResponse } from '@/types/invoice'

export const invoiceService = {
  async list(filters: Partial<InvoiceFilters> = {}): Promise<InvoiceListResponse> {
    const params: Record<string, unknown> = {}
    if (filters.status)      params.status      = filters.status
    if (filters.property_id) params.property_id = filters.property_id
    if (filters.tenant_id)   params.tenant_id   = filters.tenant_id
    if (filters.contract_id) params.contract_id = filters.contract_id
    if (filters.month)       params.month       = filters.month
    if (filters.page)        params.page        = filters.page
    if (filters.per_page)    params.per_page    = filters.per_page

    const response = await api.get<InvoiceListResponse>('/api/v1/invoices', { params })
    return response.data
  },

  async get(id: string): Promise<InvoiceResponse> {
    const response = await api.get<InvoiceResponse>(`/api/v1/invoices/${id}`)
    return response.data
  },

  async send(id: string): Promise<InvoiceResponse> {
    const response = await api.post<InvoiceResponse>(`/api/v1/invoices/${id}/send`)
    return response.data
  },

  async downloadDocument(id: string, invoiceNumber: string): Promise<void> {
    const response = await api.get(`/api/v1/invoices/${id}/document`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${invoiceNumber}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  },
}
