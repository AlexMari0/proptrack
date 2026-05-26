// ─── Core Property Types ───────────────────────────────────────────────────────

export type PropertyType = 'kos' | 'apartment' | 'ruko'
export type PropertyStatus = 'available' | 'occupied' | 'maintenance'

export interface PropertyOwner {
  id: string
  name: string
  email: string
}

export interface PropertyPhoto {
  id: number
  url: string
  thumbnail_url: string
}

export interface Property {
  id: string
  name: string
  address: string
  type: PropertyType
  status: PropertyStatus
  latitude: number
  longitude: number
  description: string | null
  monthly_price: number
  owner: PropertyOwner
  photos: PropertyPhoto[]
  created_at: string
  updated_at: string
}

// ─── API Request Payloads ──────────────────────────────────────────────────────

export interface StorePropertyPayload {
  name: string
  address: string
  type: PropertyType
  status: PropertyStatus
  latitude: number
  longitude: number
  description?: string
  monthly_price: number
}

export type UpdatePropertyPayload = StorePropertyPayload

// ─── API Response Shapes ───────────────────────────────────────────────────────

export interface PropertyMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PropertyListResponse {
  data: Property[]
  meta: PropertyMeta
  message: string
}

export interface PropertyResponse {
  data: Property
  message: string
}

export interface PropertyPhotoResponse {
  data: PropertyPhoto
  message: string
}

// ─── Store / UI State ──────────────────────────────────────────────────────────

export interface PropertyFilters {
  search: string
  status: PropertyStatus | ''
  type: PropertyType | ''
  page: number
  per_page: number
  has_active_lease?: boolean
}
