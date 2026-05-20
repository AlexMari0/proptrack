import { defineStore } from 'pinia'
import type { Property, PropertyMeta, PropertyFilters } from '@/types/property'

export const usePropertyStore = defineStore('property', {
  state: () => ({
    // List state
    properties: [] as Property[],
    meta: null as PropertyMeta | null,
    filters: {
      search: '',
      status: '',
      type: '',
      page: 1,
      per_page: 12,
    } as PropertyFilters,

    // Detail state
    selectedProperty: null as Property | null,

    // UI state
    isLoading: false,
    isSubmitting: false,
    error: null as string | null,
  }),

  getters: {
    hasProperties: (state): boolean => state.properties.length > 0,
    totalPages: (state): number => state.meta?.last_page ?? 1,
    isLastPage: (state): boolean =>
      state.meta ? state.meta.current_page >= state.meta.last_page : true,
  },

  actions: {
    setProperties(properties: Property[], meta: PropertyMeta) {
      this.properties = properties
      this.meta = meta
    },

    setSelectedProperty(property: Property | null) {
      this.selectedProperty = property
    },

    addProperty(property: Property) {
      this.properties.unshift(property)
      if (this.meta) this.meta.total += 1
    },

    updateProperty(updated: Property) {
      const index = this.properties.findIndex((p) => p.id === updated.id)
      if (index !== -1) this.properties[index] = updated
      if (this.selectedProperty?.id === updated.id) {
        this.selectedProperty = updated
      }
    },

    removeProperty(id: string) {
      this.properties = this.properties.filter((p) => p.id !== id)
      if (this.meta) this.meta.total = Math.max(0, this.meta.total - 1)
      if (this.selectedProperty?.id === id) this.selectedProperty = null
    },

    setFilters(filters: Partial<PropertyFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    resetFilters() {
      this.filters = { search: '', status: '', type: '', page: 1, per_page: 12 }
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
