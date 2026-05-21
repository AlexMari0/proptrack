import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ticketService } from '@/services/ticketService'
import { useTicketStore } from '@/stores/ticket'
import type { TicketFilters, CreateTicketPayload, UpdateTicketStatusPayload } from '@/types/ticket'

export function useTicket() {
  const store = useTicketStore()
  const router = useRouter()

  // ─── Exposed State (read-only refs) ────────────────────────────────────────
  const tickets = computed(() => store.tickets)
  const selectedTicket = computed(() => store.selectedTicket)
  const meta = computed(() => store.meta)
  const filters = computed(() => store.filters)
  const isLoading = computed(() => store.isLoading)
  const isSubmitting = computed(() => store.isSubmitting)
  const error = computed(() => store.error)

  // ─── Fetch List ────────────────────────────────────────────────────────────

  async function fetchTickets(filterOverrides: Partial<TicketFilters> = {}): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const activeFilters = { ...store.filters, ...filterOverrides }
      const response = await ticketService.list(activeFilters)
      store.setTickets(response.data, response.meta)
    } catch {
      store.setError('Failed to load tickets. Please try again.')
    } finally {
      store.setLoading(false)
    }
  }

  // ─── Fetch Single ──────────────────────────────────────────────────────────

  async function fetchTicket(id: string): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    store.setSelectedTicket(null)
    try {
      const response = await ticketService.get(id)
      store.setSelectedTicket(response.data)
    } catch {
      store.setError('Ticket not found.')
    } finally {
      store.setLoading(false)
    }
  }

  // ─── Create ────────────────────────────────────────────────────────────────

  async function createTicket(payload: CreateTicketPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await ticketService.create(payload)
      store.addTicket(response.data)
      router.push({ name: 'ticket-detail', params: { id: response.data.id } })
      return true
    } catch (err: any) {
      const message = err.response?.data?.message || 'Failed to create ticket. Please check your input.'
      store.setError(message)
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Update Status & Assignment ────────────────────────────────────────────

  async function updateTicketStatus(id: string, payload: UpdateTicketStatusPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await ticketService.updateStatus(id, payload)
      store.updateTicket(response.data)
      return true
    } catch (err: any) {
      const message = err.response?.data?.message || 'Failed to update ticket status.'
      store.setError(message)
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Add Comment ───────────────────────────────────────────────────────────

  async function addComment(id: string, content: string): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await ticketService.addComment(id, content)
      store.addCommentToSelectedTicket(response.data)
      return true
    } catch (err: any) {
      const message = err.response?.data?.message || 'Failed to add comment.'
      store.setError(message)
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Filters ───────────────────────────────────────────────────────────────

  async function applyFilters(updates: Partial<TicketFilters>): Promise<void> {
    store.setFilters({ ...updates, page: 1 })
    await fetchTickets()
  }

  async function changePage(page: number): Promise<void> {
    store.setFilters({ page })
    await fetchTickets()
  }

  function resetFilters(): void {
    store.resetFilters()
  }

  return {
    // State
    tickets,
    selectedTicket,
    meta,
    filters,
    isLoading,
    isSubmitting,
    error,
    // Actions
    fetchTickets,
    fetchTicket,
    createTicket,
    updateTicketStatus,
    addComment,
    applyFilters,
    changePage,
    resetFilters,
  }
}
