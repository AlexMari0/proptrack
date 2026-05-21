import { defineStore } from 'pinia'
import type { Ticket, TicketMeta, TicketFilters, TicketComment } from '@/types/ticket'

export const useTicketStore = defineStore('ticket', {
  state: () => ({
    // List state
    tickets: [] as Ticket[],
    meta: null as TicketMeta | null,
    filters: {
      search: '',
      status: '',
      priority: '',
      category: '',
      property_id: '',
      page: 1,
      per_page: 12,
    } as TicketFilters,

    // Detail state
    selectedTicket: null as Ticket | null,

    // UI state
    isLoading: false,
    isSubmitting: false,
    error: null as string | null,
  }),

  getters: {
    hasTickets: (state): boolean => state.tickets.length > 0,
    totalPages: (state): number => state.meta?.last_page ?? 1,
    isLastPage: (state): boolean =>
      state.meta ? state.meta.current_page >= state.meta.last_page : true,
  },

  actions: {
    setTickets(tickets: Ticket[], meta: TicketMeta) {
      this.tickets = tickets
      this.meta = meta
    },

    setSelectedTicket(ticket: Ticket | null) {
      this.selectedTicket = ticket
    },

    addTicket(ticket: Ticket) {
      this.tickets.unshift(ticket)
      if (this.meta) this.meta.total += 1
    },

    updateTicket(updated: Ticket) {
      const index = this.tickets.findIndex((t) => t.id === updated.id)
      if (index !== -1) this.tickets[index] = updated
      if (this.selectedTicket?.id === updated.id) {
        this.selectedTicket = {
          ...this.selectedTicket,
          ...updated,
          comments: this.selectedTicket.comments // preserve comments
        }
      }
    },

    addCommentToSelectedTicket(comment: TicketComment) {
      if (this.selectedTicket) {
        if (!this.selectedTicket.comments) {
          this.selectedTicket.comments = []
        }
        this.selectedTicket.comments.push(comment)
      }
    },

    setFilters(filters: Partial<TicketFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    resetFilters() {
      this.filters = {
        search: '',
        status: '',
        priority: '',
        category: '',
        property_id: '',
        page: 1,
        per_page: 12,
      }
    },

    setLoading(value: boolean) {
      this.isLoading = value
    },

    setSubmitting(value: boolean) {
      this.isSubmitting = value
    },

    setError(message: string | null) {
      this.error = message
    },
  },
})
