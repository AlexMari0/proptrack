<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, useField } from 'vee-validate'
import * as z from 'zod'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import type { RegisterPayload } from '@/types/auth'

const authStore = useAuthStore()
const loading = ref(false)
const apiError = ref<string | null>(null)
const apiSuccess = ref<string | null>(null)
const showPassword = ref(false)

// Role options computed dynamically based on current user roles
const availableRoles = computed(() => {
  const roles = authStore.user?.roles || []
  if (roles.includes('admin')) {
    return ['admin', 'owner', 'agent', 'tenant']
  }
  if (roles.includes('owner')) {
    return ['agent']
  }
  return []
})

// Set correct default role based on current user roles
const defaultRole = computed(() => {
  if (authStore.user?.roles?.includes('owner')) {
    return 'agent'
  }
  return 'owner'
})

// Contextual helper description for selected role
const roleHelperText = computed(() => {
  switch (role.value) {
    case 'admin':
      return 'Admin can manage all system data, properties, users, and financials.'
    case 'owner':
      return 'Owner can manage their own properties, review contracts, invoices, and financial reports.'
    case 'agent':
      return 'Agent can handle tenant requests, support tickets, and view property listings.'
    case 'tenant':
      return 'Tenant can access assigned property, invoices, contracts, and tickets only.'
    default:
      return ''
  }
})

// Zod Schema for validation
const registerSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  email: z.string().min(1, 'Email is required').email('Invalid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string().min(1, 'Password confirmation is required'),
  role: z.string().min(1, 'Role is required'),
})

// Custom vee-validate validation adapter utilizing Zod
const validationSchema = {
  name(value: string) {
    if (!value) return 'Name is required'
    const res = registerSchema.shape.name.safeParse(value)
    return res.success ? true : res.error.issues[0].message
  },
  email(value: string) {
    if (!value) return 'Email is required'
    const res = registerSchema.shape.email.safeParse(value)
    return res.success ? true : res.error.issues[0].message
  },
  password(value: string) {
    if (!value) return 'Password is required'
    const res = registerSchema.shape.password.safeParse(value)
    return res.success ? true : res.error.issues[0].message
  },
  password_confirmation(value: string, context: any) {
    if (!value) return 'Confirming password is required'
    if (value !== context.form.password) return "Passwords don't match"
    return true
  },
  role(value: string) {
    if (!value) return 'Please select a role'
    return true
  }
}

// Setup vee-validate form with explicit RegisterPayload type
const { handleSubmit, errors, resetForm } = useForm<RegisterPayload>({
  validationSchema,
  initialValues: {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: defaultRole.value, // Default role selection based on current user
  }
})

const { value: name } = useField<string>('name')
const { value: email } = useField<string>('email')
const { value: password } = useField<string>('password')
const { value: password_confirmation } = useField<string>('password_confirmation')
const { value: role } = useField<string>('role')

// Password Live Verification computed indicators
const passwordLengthValid = computed(() => (password.value || '').length >= 8)

const passwordsMatch = computed(() => {
  if (!password.value || !password_confirmation.value) return null
  return password.value === password_confirmation.value
})

const onSubmit = handleSubmit(async (values) => {
  loading.value = true
  apiError.value = null
  apiSuccess.value = null
  try {
    await authService.register({
      name: values.name,
      email: values.email,
      password: values.password,
      password_confirmation: values.password_confirmation,
      role: values.role,
    })
    apiSuccess.value = `Successfully registered new ${values.role} user: ${values.name}!`
    resetForm({
      values: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: defaultRole.value,
      }
    })
  } catch (err: any) {
    console.error('Registration submission failed:', err)
    apiError.value = err.response?.data?.message || 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-content" style="max-width: 860px; margin: 0 auto;">
    <!-- Back Link to Dashboard -->
    <RouterLink to="/dashboard" class="back-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="m15 18-6-6 6-6"/>
      </svg>
      Back to Dashboard
    </RouterLink>

    <div class="page-header" style="margin-bottom: 24px;">
      <div>
        <h1 class="page-title">Create User Account</h1>
        <p class="page-subtitle">Provision a secure system account for a new admin, owner, agent, or tenant</p>
      </div>
    </div>

    <!-- Main Registration Card -->
    <div class="card" style="padding: 32px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); background: #ffffff;">
      <!-- Global API success alert -->
      <div 
        v-if="apiSuccess" 
        class="alert alert--success mb-6"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="alert-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span>{{ apiSuccess }}</span>
      </div>

      <!-- Global API error alert -->
      <div 
        v-if="apiError" 
        class="alert alert--error mb-6"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="alert-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <span>{{ apiError }}</span>
      </div>

      <form @submit.prevent="onSubmit" class="space-y-6" novalidate>
        <div class="form-grid">
          <!-- Full Name Field -->
          <div class="form-col">
            <label for="name" class="form-label mb-2">Full Name <span style="color: #dc2626;">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400); pointer-events: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
              </span>
              <input 
                id="name"
                v-model="name"
                type="text"
                autocomplete="name"
                placeholder="Budi Santoso"
                :disabled="loading"
                class="form-input pl-10"
                :class="{ 'input-error': errors.name }"
              />
            </div>
            <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
          </div>

          <!-- Email Field -->
          <div class="form-col">
            <label for="email" class="form-label mb-2">Email Address <span style="color: #dc2626;">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400); pointer-events: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
              </span>
              <input 
                id="email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                :disabled="loading"
                class="form-input pl-10"
                :class="{ 'input-error': errors.email }"
              />
            </div>
            <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
          </div>

          <!-- Select Role Field (Spans full width) -->
          <div class="form-col form-col--full">
            <label class="form-label mb-2">User Role Selection <span style="color: #dc2626;">*</span></label>
            <div class="role-grid">
              <label 
                v-for="r in availableRoles" 
                :key="r"
                class="role-card"
                :class="{ 'role-card--active': role === r }"
              >
                <input 
                  type="radio" 
                  name="role" 
                  :value="r" 
                  v-model="role" 
                  class="sr-only"
                />
                <span class="role-text">{{ r }}</span>
              </label>
            </div>
            <p v-if="errors.role" class="form-error">{{ errors.role }}</p>
          </div>

          <!-- Dynamic Role Context Helper Info -->
          <div class="form-col form-col--full" v-if="roleHelperText">
            <div class="role-info-banner">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="info-icon" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/>
              </svg>
              <span>{{ roleHelperText }}</span>
            </div>
          </div>

          <!-- Password Field -->
          <div class="form-col">
            <label for="password" class="form-label mb-2">Password <span style="color: #dc2626;">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400); pointer-events: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0V10.5m-2.25 0h13.5m-13.5 0a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25m-13.5 0h13.5" />
                </svg>
              </span>
              <input 
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Min 8 characters"
                :disabled="loading"
                class="form-input pl-10 pr-10"
                :class="{ 'input-error': errors.password }"
              />
              <button 
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-g400 hover:text-g600 transition-colors"
                style="color: var(--g400); background: none; border: none; cursor: pointer;"
              >
                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815 3.183 3.183m-3.183-3.183-2.14-2.14m-2.533-2.533-3.854-3.854m0 0a3 3 0 1 0 4.243 4.242" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
              </button>
            </div>
            <div class="live-indicator-row">
              <span class="live-badge" :class="passwordLengthValid ? 'live-badge--valid' : 'live-badge--neutral'">
                <svg v-if="passwordLengthValid" class="badge-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span v-else class="badge-dot"></span>
                Password must contain at least 8 characters
              </span>
            </div>
            <p v-if="errors.password" class="form-error">{{ errors.password }}</p>
          </div>

          <!-- Confirm Password Field -->
          <div class="form-col">
            <label for="password_confirmation" class="form-label mb-2">Confirm Password <span style="color: #dc2626;">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400); pointer-events: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0V10.5m-2.25 0h13.5m-13.5 0a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25m-13.5 0h13.5" />
                </svg>
              </span>
              <input 
                id="password_confirmation"
                v-model="password_confirmation"
                type="password"
                placeholder="••••••••"
                :disabled="loading"
                class="form-input pl-10"
                :class="{ 'input-error': errors.password_confirmation }"
              />
            </div>
            <div class="live-indicator-row">
              <span v-if="password && password_confirmation" class="live-badge" :class="passwordsMatch ? 'live-badge--valid' : 'live-badge--invalid'">
                <svg v-if="passwordsMatch" class="badge-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                <svg v-else class="badge-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
              </span>
            </div>
            <p v-if="errors.password_confirmation" class="form-error">{{ errors.password_confirmation }}</p>
          </div>
        </div>

        <!-- Submit and Cancel Buttons -->
        <div style="margin-top: 32px; display: flex; gap: 16px; align-items: center;">
          <button 
            type="submit"
            :disabled="loading"
            class="btn-primary"
            style="padding: 12px 24px; font-weight: 700;"
          >
            <span v-if="loading" class="spinner" style="margin-right: 6px;" />
            {{ loading ? 'Registering User...' : 'Register User' }}
          </button>
          <RouterLink to="/dashboard" class="btn-cancel">Cancel</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.form-input {
  padding-left: 38px;
}
.input-error {
  border-color: #dc2626 !important;
}

/* Multi-column CSS Grid Layout */
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px 24px;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}

.form-col {
  display: flex;
  flex-direction: column;
}

.form-col--full {
  grid-column: span 2;
}

@media (max-width: 640px) {
  .form-col--full {
    grid-column: span 1;
  }
}

.role-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

@media (max-width: 480px) {
  .role-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.role-card {
  border: 1px solid var(--g200);
  background: #fff;
  border-radius: 12px;
  padding: 14px 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: center;
  user-select: none;
  box-sizing: border-box;
}

.role-card:hover {
  border-color: var(--g400);
  background: var(--g50);
}

.role-card--active {
  border: 1px solid var(--g900) !important;
  background: var(--g900) !important;
  box-shadow: 0 4px 12px rgba(26, 23, 18, 0.16);
}

.role-text {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  color: var(--g600);
  transition: color 0.15s;
}

.role-card--active .role-text {
  color: #ffffff !important;
  font-weight: 700;
}

/* Premium Warm Role Info Callout Banner */
.role-info-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  background: #fbf9f4;
  border: 1px solid rgba(224, 156, 26, 0.15);
  border-left: 4px solid var(--amber);
  border-radius: 10px;
  padding: 14px 16px;
  color: var(--g700);
  font-size: 0.78rem;
  font-weight: 500;
  line-height: 1.45;
  box-sizing: border-box;
  animation: slideFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.info-icon {
  width: 16px;
  height: 16px;
  color: var(--amber);
  flex-shrink: 0;
  margin-top: 1.5px;
}

@keyframes slideFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Inline Live Indicators */
.live-indicator-row {
  display: flex;
  margin-top: 6px;
  min-height: 18px;
}

.live-badge {
  font-size: 0.72rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.live-badge--valid {
  color: #059669;
}

.live-badge--invalid {
  color: #dc2626;
}

.live-badge--neutral {
  color: var(--g400);
}

.badge-icon {
  width: 12px;
  height: 12px;
  stroke-width: 3px;
  flex-shrink: 0;
}

.badge-dot {
  width: 5px;
  height: 5px;
  background-color: var(--g300);
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
  box-sizing: border-box;
}

.alert--error {
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #dc2626;
}

.alert--success {
  background: rgba(16, 185, 129, 0.06);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #059669;
}

.alert-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

/* Cancel Button styling matching design system */
.btn-cancel {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--g500);
  border: none;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Outfit', var(--font-sans);
  transition: color 0.15s ease, background-color 0.15s ease;
  padding: 9px 16px;
  border-radius: 10px;
  text-decoration: none;
}
.btn-cancel:hover {
  color: var(--g900);
  background-color: var(--g50);
}
</style>
