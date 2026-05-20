import { defineStore } from 'pinia'
import type { User } from '@/types/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: localStorage.getItem('token') as string | null,
  }),

  getters: {
    isAuthenticated: (state): boolean => !!state.token,
  },

  actions: {
    loginState(user: User, token: string) {
      this.user = user
      this.token = token
      localStorage.setItem('token', token)
    },

    logoutState() {
      this.user = null
      this.token = null
      localStorage.removeItem('token')
    },

    setUser(user: User | null) {
      this.user = user
    },
  },
})
