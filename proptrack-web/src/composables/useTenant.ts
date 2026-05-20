import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { tenantService } from '@/services/tenantService'
import { useTenantStore } from '@/stores/tenant'
import type { TenantFilters, StoreTenantPayload, UpdateTenantPayload } from '@/types/tenant'

export function useTenant() {
  const store = useTenantStore()
  const router = useRouter()

  const tenants = computed(() => store.tenants)
  const selectedTenant = computed(() => store.selectedTenant)
  const meta = computed(() => store.meta)
  const filters = computed(() => store.filters)
  const isLoading = computed(() => store.isLoading)
  const isSubmitting = computed(() => store.isSubmitting)
  const error = computed(() => store.error)

  async function fetchTenants(overrides: Partial<TenantFilters> = {}): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const activeFilters = { ...store.filters, ...overrides }
      const response = await tenantService.list(activeFilters)
      store.setTenants(response.data, response.meta)
    } catch {
      store.setError('Failed to load tenants. Please try again.')
    } finally {
      store.setLoading(false)
    }
  }

  async function fetchTenant(id: string): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    store.setSelectedTenant(null)
    try {
      const response = await tenantService.get(id)
      store.setSelectedTenant(response.data)
    } catch {
      store.setError('Tenant not found.')
    } finally {
      store.setLoading(false)
    }
  }

  async function createTenant(payload: StoreTenantPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await tenantService.create(payload)
      store.addTenant(response.data)
      router.push({ name: 'tenant-detail', params: { id: response.data.id } })
      return true
    } catch {
      store.setError('Failed to create tenant. Please check your input.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function updateTenant(id: string, payload: UpdateTenantPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await tenantService.update(id, payload)
      store.updateTenant(response.data)
      router.push({ name: 'tenant-detail', params: { id } })
      return true
    } catch {
      store.setError('Failed to update tenant.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function deleteTenant(id: string): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      await tenantService.remove(id)
      store.removeTenant(id)
      router.push({ name: 'tenants' })
      return true
    } catch {
      store.setError('Failed to delete tenant.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function applyFilters(updates: Partial<TenantFilters>): Promise<void> {
    store.setFilters({ ...updates, page: 1 })
    await fetchTenants()
  }

  async function changePage(page: number): Promise<void> {
    store.setFilters({ page })
    await fetchTenants()
  }

  function resetFilters(): void {
    store.resetFilters()
  }

  return {
    tenants, selectedTenant, meta, filters, isLoading, isSubmitting, error,
    fetchTenants, fetchTenant, createTenant, updateTenant, deleteTenant,
    applyFilters, changePage, resetFilters,
  }
}
