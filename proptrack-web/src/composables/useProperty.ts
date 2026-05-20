import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { propertyService } from '@/services/propertyService'
import { usePropertyStore } from '@/stores/property'
import type { PropertyFilters, StorePropertyPayload, UpdatePropertyPayload } from '@/types/property'

export function useProperty() {
  const store = usePropertyStore()
  const router = useRouter()

  // ─── Exposed State (read-only refs) ────────────────────────────────────────
  const properties = computed(() => store.properties)
  const selectedProperty = computed(() => store.selectedProperty)
  const meta = computed(() => store.meta)
  const filters = computed(() => store.filters)
  const isLoading = computed(() => store.isLoading)
  const isSubmitting = computed(() => store.isSubmitting)
  const error = computed(() => store.error)

  // ─── Fetch List ────────────────────────────────────────────────────────────

  async function fetchProperties(filterOverrides: Partial<PropertyFilters> = {}): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const activeFilters = { ...store.filters, ...filterOverrides }
      const response = await propertyService.list(activeFilters)
      store.setProperties(response.data, response.meta)
    } catch {
      store.setError('Failed to load properties. Please try again.')
    } finally {
      store.setLoading(false)
    }
  }

  // ─── Fetch Single ──────────────────────────────────────────────────────────

  async function fetchProperty(id: string): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    store.setSelectedProperty(null)
    try {
      const response = await propertyService.get(id)
      store.setSelectedProperty(response.data)
    } catch {
      store.setError('Property not found.')
    } finally {
      store.setLoading(false)
    }
  }

  // ─── Create ────────────────────────────────────────────────────────────────

  async function createProperty(payload: StorePropertyPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await propertyService.create(payload)
      store.addProperty(response.data)
      router.push({ name: 'property-detail', params: { id: response.data.id } })
      return true
    } catch {
      store.setError('Failed to create property. Please check your input.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Update ────────────────────────────────────────────────────────────────

  async function updateProperty(id: string, payload: UpdatePropertyPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await propertyService.update(id, payload)
      store.updateProperty(response.data)
      router.push({ name: 'property-detail', params: { id } })
      return true
    } catch {
      store.setError('Failed to update property.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Delete ────────────────────────────────────────────────────────────────

  async function deleteProperty(id: string): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      await propertyService.remove(id)
      store.removeProperty(id)
      router.push({ name: 'properties' })
      return true
    } catch {
      store.setError('Failed to delete property.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Photos ────────────────────────────────────────────────────────────────

  async function uploadPhoto(propertyId: string, file: File): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await propertyService.uploadPhoto(propertyId, file)
      // Append the new photo to the selected property's photos array
      if (store.selectedProperty?.id === propertyId) {
        store.selectedProperty.photos.push(response.data)
      }
      return true
    } catch {
      store.setError('Failed to upload photo.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function deletePhoto(propertyId: string, mediaId: number): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      await propertyService.deletePhoto(propertyId, mediaId)
      // Remove photo from selected property state
      if (store.selectedProperty?.id === propertyId) {
        store.selectedProperty.photos = store.selectedProperty.photos.filter(
          (p) => p.id !== mediaId,
        )
      }
      return true
    } catch {
      store.setError('Failed to delete photo.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  // ─── Filters ───────────────────────────────────────────────────────────────

  async function applyFilters(updates: Partial<PropertyFilters>): Promise<void> {
    // Reset to page 1 whenever filters change
    store.setFilters({ ...updates, page: 1 })
    await fetchProperties()
  }

  async function changePage(page: number): Promise<void> {
    store.setFilters({ page })
    await fetchProperties()
  }

  function resetFilters(): void {
    store.resetFilters()
  }

  return {
    // State
    properties,
    selectedProperty,
    meta,
    filters,
    isLoading,
    isSubmitting,
    error,
    // Actions
    fetchProperties,
    fetchProperty,
    createProperty,
    updateProperty,
    deleteProperty,
    uploadPhoto,
    deletePhoto,
    applyFilters,
    changePage,
    resetFilters,
  }
}
