import { defineStore } from 'pinia'
import type { Tenant, TenantMeta, TenantFilters } from '@/types/tenant'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    tenants: [] as Tenant[],
    meta: null as TenantMeta | null,
    filters: {
      search: '',
      page: 1,
      per_page: 15,
    } as TenantFilters,

    selectedTenant: null as Tenant | null,

    isLoading: false,
    isSubmitting: false,
    error: null as string | null,
  }),

  getters: {
    hasTenants: (state): boolean => state.tenants.length > 0,
    totalPages: (state): number => state.meta?.last_page ?? 1,
  },

  actions: {
    setTenants(tenants: Tenant[], meta: TenantMeta) {
      this.tenants = tenants
      this.meta = meta
    },

    setSelectedTenant(tenant: Tenant | null) {
      this.selectedTenant = tenant
    },

    addTenant(tenant: Tenant) {
      this.tenants.unshift(tenant)
      if (this.meta) this.meta.total += 1
    },

    updateTenant(updated: Tenant) {
      const index = this.tenants.findIndex((t) => t.id === updated.id)
      if (index !== -1) this.tenants[index] = updated
      if (this.selectedTenant?.id === updated.id) this.selectedTenant = updated
    },

    removeTenant(id: string) {
      this.tenants = this.tenants.filter((t) => t.id !== id)
      if (this.meta) this.meta.total = Math.max(0, this.meta.total - 1)
      if (this.selectedTenant?.id === id) this.selectedTenant = null
    },

    setFilters(filters: Partial<TenantFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    resetFilters() {
      this.filters = { search: '', page: 1, per_page: 15 }
    },

    setLoading(value: boolean) { this.isLoading = value },
    setSubmitting(value: boolean) { this.isSubmitting = value },
    setError(message: string | null) { this.error = message },
  },
})
