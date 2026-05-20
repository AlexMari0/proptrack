import api from '@/plugins/axios'
import type {
  RegisterPayload,
  LoginPayload,
  AuthResponse,
  UserResponse,
} from '@/types/auth'

export const authService = {
  async register(payload: RegisterPayload): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/api/v1/auth/register', payload)
    return response.data
  },

  async login(payload: LoginPayload): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/api/v1/auth/login', payload)
    return response.data
  },

  async logout(): Promise<{ message: string }> {
    const response = await api.post<{ message: string }>('/api/v1/auth/logout')
    return response.data
  },

  async me(): Promise<UserResponse> {
    const response = await api.get<UserResponse>('/api/v1/auth/me')
    return response.data
  },
}
