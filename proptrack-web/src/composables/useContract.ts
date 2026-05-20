import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { contractService } from '@/services/contractService'
import { useContractStore } from '@/stores/contract'
import type { ContractFilters, StoreContractPayload, UpdateContractPayload } from '@/types/contract'

export function useContract() {
  const store = useContractStore()
  const router = useRouter()

  const contracts = computed(() => store.contracts)
  const selectedContract = computed(() => store.selectedContract)
  const meta = computed(() => store.meta)
  const filters = computed(() => store.filters)
  const isLoading = computed(() => store.isLoading)
  const isSubmitting = computed(() => store.isSubmitting)
  const error = computed(() => store.error)

  async function fetchContracts(overrides: Partial<ContractFilters> = {}): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const active = { ...store.filters, ...overrides }
      const response = await contractService.list(active)
      store.setContracts(response.data, response.meta)
    } catch {
      store.setError('Failed to load contracts.')
    } finally {
      store.setLoading(false)
    }
  }

  async function fetchContract(id: string): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    store.setSelectedContract(null)
    try {
      const response = await contractService.get(id)
      store.setSelectedContract(response.data)
    } catch {
      store.setError('Contract not found.')
    } finally {
      store.setLoading(false)
    }
  }

  async function createContract(payload: StoreContractPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await contractService.create(payload)
      store.addContract(response.data)
      router.push({ name: 'contract-detail', params: { id: response.data.id } })
      return true
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      store.setError(msg ?? 'Failed to create contract. Check for an active contract on this property.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function updateContract(id: string, payload: UpdateContractPayload): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await contractService.update(id, payload)
      store.updateContract(response.data)
      router.push({ name: 'contract-detail', params: { id } })
      return true
    } catch {
      store.setError('Failed to update contract.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function terminateContract(id: string): Promise<boolean> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      const response = await contractService.terminate(id)
      store.updateContract(response.data)
      return true
    } catch {
      store.setError('Failed to terminate contract.')
      return false
    } finally {
      store.setSubmitting(false)
    }
  }

  async function downloadDocument(id: string): Promise<void> {
    store.setSubmitting(true)
    store.setError(null)
    try {
      await contractService.downloadDocument(id)
    } catch {
      store.setError('Failed to download contract PDF.')
    } finally {
      store.setSubmitting(false)
    }
  }

  async function applyFilters(updates: Partial<ContractFilters>): Promise<void> {
    store.setFilters({ ...updates, page: 1 })
    await fetchContracts()
  }

  async function changePage(page: number): Promise<void> {
    store.setFilters({ page })
    await fetchContracts()
  }

  function resetFilters(): void {
    store.resetFilters()
  }

  return {
    contracts, selectedContract, meta, filters, isLoading, isSubmitting, error,
    fetchContracts, fetchContract, createContract, updateContract,
    terminateContract, downloadDocument, applyFilters, changePage, resetFilters,
  }
}
