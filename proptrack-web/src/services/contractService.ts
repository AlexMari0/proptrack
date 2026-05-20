import api from '@/plugins/axios'
import type {
  ContractListResponse,
  ContractResponse,
  StoreContractPayload,
  UpdateContractPayload,
  ContractFilters,
} from '@/types/contract'

export const contractService = {
  async list(filters: Partial<ContractFilters> = {}): Promise<ContractListResponse> {
    const params: Record<string, unknown> = {}
    if (filters.status) params.status = filters.status
    if (filters.tenant_id) params.tenant_id = filters.tenant_id
    if (filters.property_id) params.property_id = filters.property_id
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page

    const response = await api.get<ContractListResponse>('/api/v1/contracts', { params })
    return response.data
  },

  async get(id: string): Promise<ContractResponse> {
    const response = await api.get<ContractResponse>(`/api/v1/contracts/${id}`)
    return response.data
  },

  async create(payload: StoreContractPayload): Promise<ContractResponse> {
    const response = await api.post<ContractResponse>('/api/v1/contracts', payload)
    return response.data
  },

  async update(id: string, payload: UpdateContractPayload): Promise<ContractResponse> {
    const response = await api.put<ContractResponse>(`/api/v1/contracts/${id}`, payload)
    return response.data
  },

  async terminate(id: string): Promise<ContractResponse> {
    const response = await api.post<ContractResponse>(`/api/v1/contracts/${id}/terminate`)
    return response.data
  },

  /**
   * Returns the blob URL for the PDF document so the browser can download it.
   */
  async downloadDocument(id: string): Promise<void> {
    const response = await api.get(`/api/v1/contracts/${id}/document`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `contract-${id}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  },
}
