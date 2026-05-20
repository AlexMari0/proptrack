import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/auth/RegisterPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/pages/DashboardPage.vue'),
      meta: { requiresAuth: true },
    },
    // ─── Properties ───────────────────────────────────────────────────────────
    {
      path: '/properties',
      name: 'properties',
      component: () => import('@/pages/properties/PropertyListPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/properties/new',
      name: 'property-new',
      component: () => import('@/pages/properties/PropertyFormPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/properties/:id',
      name: 'property-detail',
      component: () => import('@/pages/properties/PropertyDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/properties/:id/edit',
      name: 'property-edit',
      component: () => import('@/pages/properties/PropertyFormPage.vue'),
      meta: { requiresAuth: true },
    },
    // ─── Tenants ──────────────────────────────────────────────────────────────
    {
      path: '/tenants',
      name: 'tenants',
      component: () => import('@/pages/tenants/TenantListPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tenants/new',
      name: 'tenant-new',
      component: () => import('@/pages/tenants/TenantFormPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tenants/:id',
      name: 'tenant-detail',
      component: () => import('@/pages/tenants/TenantDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tenants/:id/edit',
      name: 'tenant-edit',
      component: () => import('@/pages/tenants/TenantFormPage.vue'),
      meta: { requiresAuth: true },
    },
    // ─── Contracts ────────────────────────────────────────────────────────────
    {
      path: '/contracts',
      name: 'contracts',
      component: () => import('@/pages/contracts/ContractListPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/contracts/new',
      name: 'contract-new',
      component: () => import('@/pages/contracts/ContractFormPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/contracts/:id',
      name: 'contract-detail',
      component: () => import('@/pages/contracts/ContractDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/contracts/:id/edit',
      name: 'contract-edit',
      component: () => import('@/pages/contracts/ContractFormPage.vue'),
      meta: { requiresAuth: true },
    },
    // Fallback route
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

// Navigation Guard
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // 1. If we have a token but no user object (e.g., page refresh), fetch the profile
  if (authStore.token && !authStore.user) {
    try {
      const response = await authService.me()
      authStore.setUser(response.data)
    } catch (error) {
      console.error('Failed to restore user session on route navigation:', error)
      authStore.logoutState()
    }
  }

  const isAuthenticated = authStore.isAuthenticated

  // 2. Route Guard checks
  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'login' })
  } else if (to.meta.guestOnly && isAuthenticated) {
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router
