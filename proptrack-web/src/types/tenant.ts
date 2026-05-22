// ─── Core Tenant Types ─────────────────────────────────────────────────────────

export interface Tenant {
  id: string
  name: string
  email: string
  phone: string
  id_card_number: string
  emergency_contact_name: string
  emergency_contact_phone: string
  active_contract: ActiveContract | null
  created_at: string
  updated_at: string
}

// Placeholder — will be fully typed in Phase 2.3
export interface ActiveContract {
  id: string
  status: 'active' | 'expired' | 'terminated'
  start_date: string
  end_date: string
  monthly_rent: number
  property: {
    id: string
    name: string
  }
}

// ─── API Request Payloads ──────────────────────────────────────────────────────

export interface StoreTenantPayload {
  name: string
  email: string
  phone: string
  id_card_number: string
  emergency_contact_name: string
  emergency_contact_phone: string
}

export type UpdateTenantPayload = StoreTenantPayload

// ─── API Response Shapes ───────────────────────────────────────────────────────

export interface TenantMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface TenantListResponse {
  data: Tenant[]
  meta: TenantMeta
  message: string
}

export interface TenantResponse {
  data: Tenant
  message: string
}

// ─── UI Filter State ───────────────────────────────────────────────────────────

export interface TenantFilters {
  search: string
  status?: 'active' | 'inactive' | ''
  page: number
  per_page: number
}
