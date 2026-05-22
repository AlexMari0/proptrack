<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import * as z from 'zod'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const { login, loading, error: apiError } = useAuth()
const showPassword = ref(false)

// Zod Schema for validation
const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Invalid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
})

// Custom vee-validate validation adapter utilizing Zod
const validationSchema = {
  email(value: string) {
    if (!value) return 'Email is required'
    const res = loginSchema.shape.email.safeParse(value)
    return res.success ? true : res.error.issues[0].message
  },
  password(value: string) {
    if (!value) return 'Password is required'
    const res = loginSchema.shape.password.safeParse(value)
    return res.success ? true : res.error.issues[0].message
  }
}

// Setup vee-validate form
const { handleSubmit, errors } = useForm({
  validationSchema,
})

const { value: email } = useField<string>('email')
const { value: password } = useField<string>('password')

const onSubmit = handleSubmit(async (values) => {
  try {
    await login({
      email: values.email,
      password: values.password,
    })
    router.push('/dashboard')
  } catch (err) {
    console.error('Login submission failed:', err)
  }
})
</script>

<template>
  <div class="min-h-dvh bg-[#eaece7] flex flex-col justify-center items-center px-4 relative overflow-hidden">
    <!-- Subtle warm background glow -->
    <div class="absolute top-[-30%] left-[-20%] w-[600px] h-[600px] rounded-full bg-amber-soft/40 blur-[130px] pointer-events-none"></div>
    <div class="absolute bottom-[-30%] right-[-20%] w-[500px] h-[500px] rounded-full bg-[#f8f6f2]/60 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md z-10">
      <!-- Logo and Header -->
      <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-g900 to-g700 items-center justify-center shadow-md mb-4 transition-transform hover:scale-105 duration-300">
          <span class="text-[#f8f6f2] font-extrabold text-2xl">P</span>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-g900 mb-2">Welcome back</h1>
        <p class="text-g500 text-sm">Sign in to manage your real estate portfolio</p>
      </div>

      <!-- Main Login Card -->
      <div class="card shadow-md relative" style="padding: 32px;">
        <!-- Global API error alert -->
        <div 
          v-if="apiError" 
          class="alert alert--error mb-6"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0 mt-0.5" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          <span>{{ apiError }}</span>
        </div>

        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Email Field -->
          <div>
            <label for="email" class="form-label mb-2">Email address</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" style="width: 16px; height: 16px;">
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

          <!-- Password Field -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label for="password" class="form-label" style="margin-bottom: 0;">Password</label>
              <!-- Dead link neutralized: no forgot-password feature exists -->
              <span class="text-[11px] text-g400 select-none" style="color: var(--g400);" title="Not available yet">Forgot password?</span>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-g400" style="color: var(--g400);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0V10.5m-2.25 0h13.5m-13.5 0a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25m-13.5 0h13.5" />
                </svg>
              </span>
              <input 
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
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
                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815 3.183 3.183m-3.183-3.183-2.14-2.14m-2.533-2.533-3.854-3.854m0 0a3 3 0 1 0 4.243 4.242" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" style="width: 16px; height: 16px;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
              </button>
            </div>
            <p v-if="errors.password" class="form-error">{{ errors.password }}</p>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit"
            :disabled="loading"
            class="btn-primary w-full justify-center"
            style="padding: 12px 18px;"
          >
            <span v-if="loading" class="spinner" />
            {{ loading ? 'Authenticating...' : 'Sign In' }}
          </button>
        </form>
      </div>

      <!-- Register Link -->
      <p class="text-center text-g500 text-xs mt-6 font-medium">
        Don't have an account? 
        <RouterLink to="/register" class="text-amber font-bold hover:underline ml-1" style="color: var(--amber);">Create an account</RouterLink>
      </p>
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
</style>
