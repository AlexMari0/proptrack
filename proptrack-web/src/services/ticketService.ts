import api from '@/plugins/axios'
import type {
  TicketListResponse,
  TicketResponse,
  TicketCommentResponse,
  CreateTicketPayload,
  UpdateTicketStatusPayload,
  TicketFilters,
} from '@/types/ticket'

export const ticketService = {
  /**
   * Fetch paginated + filtered list of tickets.
   */
  async list(filters: Partial<TicketFilters> = {}): Promise<TicketListResponse> {
    const params: Record<string, unknown> = {}
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.priority) params.priority = filters.priority
    if (filters.category) params.category = filters.category
    if (filters.property_id) params.property_id = filters.property_id
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page

    const response = await api.get<TicketListResponse>('/api/v1/tickets', { params })
    return response.data
  },

  /**
   * Fetch a single ticket by ID (including comments).
   */
  async get(id: string): Promise<TicketResponse> {
    const response = await api.get<TicketResponse>(`/api/v1/tickets/${id}`)
    return response.data
  },

  /**
   * Create a new complaint ticket.
   */
  async create(payload: CreateTicketPayload): Promise<TicketResponse> {
    const response = await api.post<TicketResponse>('/api/v1/tickets', payload)
    return response.data
  },

  /**
   * Update the status / assignment of a ticket.
   */
  async updateStatus(id: string, payload: UpdateTicketStatusPayload): Promise<TicketResponse> {
    const response = await api.put<TicketResponse>(`/api/v1/tickets/${id}/status`, payload)
    return response.data
  },

  /**
   * Add a comment to a ticket.
   */
  async addComment(id: string, content: string): Promise<TicketCommentResponse> {
    const response = await api.post<TicketCommentResponse>(`/api/v1/tickets/${id}/comments`, {
      content,
    })
    return response.data;
  },
}
