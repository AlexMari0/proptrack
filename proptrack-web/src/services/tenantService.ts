import api from '@/plugins/axios'
import type {
  TenantListResponse,
  TenantResponse,
  StoreTenantPayload,
  UpdateTenantPayload,
  TenantFilters,
} from '@/types/tenant'

export const tenantService = {
  async list(filters: Partial<TenantFilters> = {}): Promise<TenantListResponse> {
    const params: Record<string, unknown> = {}
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page

    const response = await api.get<TenantListResponse>('/api/v1/tenants', { params })
    return response.data
  },

  async get(id: string): Promise<TenantResponse> {
    const response = await api.get<TenantResponse>(`/api/v1/tenants/${id}`)
    return response.data
  },

  async create(payload: StoreTenantPayload): Promise<TenantResponse> {
    const response = await api.post<TenantResponse>('/api/v1/tenants', payload)
    return response.data
  },

  async update(id: string, payload: UpdateTenantPayload): Promise<TenantResponse> {
    const response = await api.put<TenantResponse>(`/api/v1/tenants/${id}`, payload)
    return response.data
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/api/v1/tenants/${id}`)
  },
}
