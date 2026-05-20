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
  <div class="min-h-screen bg-[#070b13] flex flex-col justify-center items-center px-4 relative overflow-hidden">
    <!-- Glowing background accents -->
    <div class="absolute top-[-30%] left-[-20%] w-[600px] h-[600px] rounded-full bg-indigo-600/10 blur-[130px] pointer-events-none"></div>
    <div class="absolute bottom-[-30%] right-[-20%] w-[600px] h-[600px] rounded-full bg-purple-600/10 blur-[130px] pointer-events-none"></div>

    <div class="w-full max-w-md z-10">
      <!-- Logo and Header -->
      <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 items-center justify-center shadow-xl shadow-indigo-500/20 mb-4 transition-transform hover:scale-105 duration-300">
          <span class="text-white font-extrabold text-2xl">P</span>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Welcome Back</h1>
        <p class="text-slate-400 text-sm font-medium">Log in to manage your real estate assets</p>
      </div>

      <!-- Main Login Card -->
      <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-8 backdrop-blur-xl shadow-2xl relative">
        <!-- Decorative subtle divider -->
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>

        <!-- Global API error alert -->
        <div 
          v-if="apiError" 
          class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold flex items-start gap-2.5 animate-fadeIn"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0 mt-0.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          <span>{{ apiError }}</span>
        </div>

        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Email Field -->
          <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">Email Address</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
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
                class="w-full pl-10 pr-4 py-3 bg-slate-950/60 border rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all duration-200"
                :class="errors.email ? 'border-rose-500/50 focus:border-rose-500 focus:ring-rose-500/10' : 'border-slate-800 focus:border-indigo-500'"
              />
            </div>
            <p v-if="errors.email" class="mt-1.5 text-xs text-rose-400 font-semibold flex items-center gap-1.5">
              <span class="w-1 h-1 rounded-full bg-rose-400"></span>
              {{ errors.email }}
            </p>
          </div>

          <!-- Password Field -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300">Password</label>
              <a href="#" class="text-[11px] font-semibold text-indigo-400 hover:text-indigo-300 transition-colors">Forgot Password?</a>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0V10.5m-2.25 0h13.5m-13.5 0a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25m-13.5 0h13.5" />
                </svg>
              </span>
              <input 
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                :disabled="loading"
                class="w-full pl-10 pr-10 py-3 bg-slate-950/60 border rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all duration-200"
                :class="errors.password ? 'border-rose-500/50 focus:border-rose-500 focus:ring-rose-500/10' : 'border-slate-800 focus:border-indigo-500'"
              />
              <button 
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors"
              >
                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815 3.183 3.183m-3.183-3.183-2.14-2.14m-2.533-2.533-3.854-3.854m0 0a3 3 0 1 0 4.243 4.242" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
              </button>
            </div>
            <p v-if="errors.password" class="mt-1.5 text-xs text-rose-400 font-semibold flex items-center gap-1.5">
              <span class="w-1 h-1 rounded-full bg-rose-400"></span>
              {{ errors.password }}
            </p>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit"
            :disabled="loading"
            class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm rounded-xl transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20"
          >
            <span v-if="loading" class="w-4 h-4 rounded-full border-2 border-white/20 border-t-white animate-spin"></span>
            {{ loading ? 'Authenticating...' : 'Sign In' }}
          </button>
        </form>
      </div>

      <!-- Register Link -->
      <p class="text-center text-slate-400 text-xs mt-6 font-medium">
        Don't have an account? 
        <RouterLink to="/register" class="text-indigo-400 font-bold hover:text-indigo-300 transition-colors ml-1">Create an account</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.25s ease-out forwards;
}
</style>
