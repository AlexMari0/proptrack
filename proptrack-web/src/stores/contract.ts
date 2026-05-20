import { defineStore } from 'pinia'
import type { Contract, ContractMeta, ContractFilters } from '@/types/contract'

export const useContractStore = defineStore('contract', {
  state: () => ({
    contracts: [] as Contract[],
    meta: null as ContractMeta | null,
    filters: {
      status: '',
      tenant_id: '',
      property_id: '',
      page: 1,
      per_page: 15,
    } as ContractFilters,

    selectedContract: null as Contract | null,

    isLoading: false,
    isSubmitting: false,
    error: null as string | null,
  }),

  getters: {
    hasContracts: (state): boolean => state.contracts.length > 0,
  },

  actions: {
    setContracts(contracts: Contract[], meta: ContractMeta) {
      this.contracts = contracts
      this.meta = meta
    },

    setSelectedContract(contract: Contract | null) {
      this.selectedContract = contract
    },

    addContract(contract: Contract) {
      this.contracts.unshift(contract)
      if (this.meta) this.meta.total += 1
    },

    updateContract(updated: Contract) {
      const index = this.contracts.findIndex((c) => c.id === updated.id)
      if (index !== -1) this.contracts[index] = updated
      if (this.selectedContract?.id === updated.id) this.selectedContract = updated
    },

    setFilters(filters: Partial<ContractFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    resetFilters() {
      this.filters = { status: '', tenant_id: '', property_id: '', page: 1, per_page: 15 }
    },

    setLoading(v: boolean) { this.isLoading = v },
    setSubmitting(v: boolean) { this.isSubmitting = v },
    setError(msg: string | null) { this.error = msg },
  },
})
