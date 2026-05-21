// ─── Core Invoice Types ────────────────────────────────────────────────────────

export type InvoiceStatus = 'unpaid' | 'paid' | 'overdue' | 'cancelled'

export interface InvoiceContract {
  id: string
}

export interface InvoiceTenant {
  id: string
  name: string
}

export interface InvoiceProperty {
  id: string
  name: string
}

export interface Invoice {
  id: string
  invoice_number: string
  status: InvoiceStatus
  amount: number
  billing_month: string   // "YYYY-MM"
  due_date: string        // "YYYY-MM-DD"
  paid_at: string | null
  payment_gateway: string | null
  contract: InvoiceContract
  tenant: InvoiceTenant
  property: InvoiceProperty
  created_at: string
  updated_at: string
}

// ─── API Response Shapes ───────────────────────────────────────────────────────

export interface InvoiceMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface InvoiceListResponse {
  data: Invoice[]
  meta: InvoiceMeta
  message: string
}

export interface InvoiceResponse {
  data: Invoice
  message: string
}

// ─── Filter State ──────────────────────────────────────────────────────────────

export interface InvoiceFilters {
  status: InvoiceStatus | ''
  property_id: string
  tenant_id: string
  month: string           // "YYYY-MM" or ''
  page: number
  per_page: number
}
