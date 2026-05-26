import api from '@/plugins/axios'
import type {
  PropertyListResponse,
  PropertyResponse,
  PropertyPhotoResponse,
  StorePropertyPayload,
  UpdatePropertyPayload,
  PropertyFilters,
} from '@/types/property'

export const propertyService = {
  /**
   * Fetch paginated + filtered list of properties.
   */
  async list(filters: Partial<PropertyFilters> = {}): Promise<PropertyListResponse> {
    // Remove empty string params so they don't pollute the query string
    const params: Record<string, unknown> = {}
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page
    if (filters.has_active_lease !== undefined) params.has_active_lease = filters.has_active_lease

    const response = await api.get<PropertyListResponse>('/api/v1/properties', { params })
    return response.data
  },

  /**
   * Fetch a single property by ID.
   */
  async get(id: string): Promise<PropertyResponse> {
    const response = await api.get<PropertyResponse>(`/api/v1/properties/${id}`)
    return response.data
  },

  /**
   * Create a new property.
   */
  async create(payload: StorePropertyPayload): Promise<PropertyResponse> {
    const response = await api.post<PropertyResponse>('/api/v1/properties', payload)
    return response.data
  },

  /**
   * Update an existing property.
   */
  async update(id: string, payload: UpdatePropertyPayload): Promise<PropertyResponse> {
    const response = await api.put<PropertyResponse>(`/api/v1/properties/${id}`, payload)
    return response.data
  },

  /**
   * Delete a property.
   */
  async remove(id: string): Promise<void> {
    await api.delete(`/api/v1/properties/${id}`)
  },

  /**
   * Upload a photo to a property (multipart/form-data).
   */
  async uploadPhoto(propertyId: string, file: File): Promise<PropertyPhotoResponse> {
    const formData = new FormData()
    formData.append('photo', file)

    const response = await api.post<PropertyPhotoResponse>(
      `/api/v1/properties/${propertyId}/photos`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    return response.data
  },

  /**
   * Delete a specific photo from a property.
   */
  async deletePhoto(propertyId: string, mediaId: number): Promise<void> {
    await api.delete(`/api/v1/properties/${propertyId}/photos/${mediaId}`)
  },
}
