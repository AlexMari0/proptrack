import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import type { LoginPayload, RegisterPayload, UpdateProfilePayload } from '@/types/auth'

export function useAuth() {
  const authStore = useAuthStore()
  const router = useRouter()
  const loading = ref(false)
  const error = ref<string | null>(null)

  const login = async (payload: LoginPayload) => {
    loading.value = true
    error.value = null
    try {
      const response = await authService.login(payload)
      authStore.loginState(response.data.user, response.data.token)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Login failed. Please check your credentials.'
      throw err
    } finally {
      loading.value = false
    }
  }

  const register = async (payload: RegisterPayload) => {
    loading.value = true
    error.value = null
    try {
      const response = await authService.register(payload)
      authStore.loginState(response.data.user, response.data.token)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Registration failed. Please try again.'
      throw err
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    loading.value = true
    try {
      await authService.logout()
    } catch (err) {
      console.error('Logout error on backend:', err)
    } finally {
      authStore.logoutState()
      loading.value = false
      router.push('/login')
    }
  }

  const fetchProfile = async () => {
    if (!authStore.token) return null
    loading.value = true
    try {
      const response = await authService.me()
      authStore.setUser(response.data)
      return response.data
    } catch (err) {
      console.error('Failed to fetch user profile:', err)
      authStore.logoutState()
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateProfile = async (payload: UpdateProfilePayload) => {
    loading.value = true
    error.value = null
    try {
      const response = await authService.updateProfile(payload)
      authStore.setUser(response.data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update profile. Please try again.'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    login,
    register,
    logout,
    fetchProfile,
    updateProfile,
    authStore,
  }
}
