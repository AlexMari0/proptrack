// ─── Core Contract Types ───────────────────────────────────────────────────────

export type ContractStatus = 'active' | 'expired' | 'terminated'

export interface ContractTenant {
  id: string
  name: string
}

export interface ContractProperty {
  id: string
  name: string
}

export interface Contract {
  id: string
  status: ContractStatus
  tenant: ContractTenant
  property: ContractProperty
  start_date: string
  end_date: string
  monthly_rent: number
  deposit_amount: number
  billing_date: number
  terminated_at: string | null
  created_at: string
  updated_at: string
}

// ─── API Request Payload ───────────────────────────────────────────────────────

export interface StoreContractPayload {
  tenant_id: string
  property_id: string
  start_date: string
  end_date: string
  monthly_rent: number
  deposit_amount: number
  billing_date: number
}

export type UpdateContractPayload = Omit<StoreContractPayload, 'tenant_id' | 'property_id'>

// ─── API Response Shapes ───────────────────────────────────────────────────────

export interface ContractMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ContractListResponse {
  data: Contract[]
  meta: ContractMeta
  message: string
}

export interface ContractResponse {
  data: Contract
  message: string
}

// ─── UI Filter State ───────────────────────────────────────────────────────────

export interface ContractFilters {
  status: ContractStatus | ''
  tenant_id: string
  property_id: string
  page: number
  per_page: number
}
