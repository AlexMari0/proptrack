export interface User {
  id: number
  name: string
  email: string
  roles: string[]
}

export interface AuthResponse {
  data: {
    token: string
    user: User
  }
  message: string
}

export interface UserResponse {
  data: User
  message: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  role: 'owner' | 'agent' | 'tenant'
}

export interface LoginPayload {
  email: string
  password: string
}
