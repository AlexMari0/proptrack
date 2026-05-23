<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForm, useField } from 'vee-validate'
import * as z from 'zod'
import { useAuth } from '@/composables/useAuth'
import authBg from '@/assets/auth_background.png'
import AppLogo from '@/components/layout/AppLogo.vue'


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
  <div class="auth-page" :style="{ backgroundImage: `url(${authBg})` }">
    <!-- Overlay for premium atmosphere and legibility -->
    <div class="auth-overlay"></div>

    <!-- Top Left Floating Brand Logo -->
    <header class="auth-brand">
      <AppLogo :size="38" show-text />
    </header>

    <div class="auth-container">
      <!-- Floating Login Card -->
      <main class="login-card shadow-lg">
        <header class="card-header">
          <h1 class="card-title">Sign In</h1>
          <p class="card-subtitle">Please enter your details to access your account</p>
        </header>

        <!-- Global API error alert -->
        <Transition name="fade">
          <div v-if="apiError" class="alert alert--error mb-4">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="alert-icon">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>{{ apiError }}</span>
          </div>
        </Transition>

        <form @submit.prevent="onSubmit" class="login-form">
          <!-- Email Field -->
          <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="name@example.com"
              :disabled="loading"
              class="form-input"
              :class="{ 'input-error': errors.email }"
            />
            <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
          </div>

          <!-- Password Field -->
          <div class="form-group">
            <div class="form-label-row">
              <label for="password" class="form-label">Password</label>
              <span class="forgot-link-neutral" title="Contact admin to reset password">Forgot password?</span>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••••••"
                :disabled="loading"
                class="form-input pr-10"
                :class="{ 'input-error': errors.password }"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="password-toggle-btn"
                :disabled="loading"
                aria-label="Toggle password visibility"
              >
                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="toggle-icon">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815 3.183 3.183m-3.183-3.183-2.14-2.14m-2.533-2.533-3.854-3.854m0 0a3 3 0 1 0 4.243 4.242" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="toggle-icon">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
              </button>
            </div>
            <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full btn-submit"
          >
            <span v-if="loading" class="spinner" />
            {{ loading ? 'Signing In...' : 'Sign In' }}
          </button>
        </form>

        <footer class="card-footer">
          <span class="footer-subtext">Are you new?</span>
          <RouterLink to="/register" class="footer-link">Create an Account</RouterLink>
        </footer>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* ─── Outer Viewport Backdrop ─────────────────────────────────────────────── */
.auth-page {
  position: relative;
  min-height: 100dvh;
  width: 100%;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding-left: 10vw;
  box-sizing: border-box;
  overflow: hidden;
  font-family: 'Outfit', var(--font-sans);
}

@media (max-width: 900px) {
  .auth-page {
    justify-content: center;
    padding-left: 0;
  }
}

/* Atmospheric Overlay Layer */
.auth-overlay {
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.4) 0%, rgba(234, 236, 231, 0.6) 60%, rgba(0, 0, 0, 0.15) 100%);
  pointer-events: none;
  z-index: 1;
  backdrop-filter: blur(1px);
}

/* ─── Top Left Overlay Brand Logo ─────────────────────────────────────────── */
.auth-brand {
  position: absolute;
  top: 32px;
  left: 40px;
  display: flex;
  align-items: center;
  gap: 12px;
  z-index: 10;
  text-decoration: none;
  pointer-events: none;
}

@media (max-width: 600px) {
  .auth-brand {
    top: 24px;
    left: 24px;
  }
}


/* Container limits */
.auth-container {
  position: relative;
  z-index: 5;
  width: 100%;
  max-width: 440px;
  padding: 20px;
  box-sizing: border-box;
}

/* ─── Floating Login Card ────────────────────────────────────────────────── */
.login-card {
  background: #fff;
  border-radius: 28px;
  padding: 40px;
  box-sizing: border-box;
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 20px 50px rgba(26, 23, 18, 0.08);
  display: flex;
  flex-direction: column;
  gap: 24px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  animation: scaleFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes scaleFadeIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

@media (max-width: 480px) {
  .login-card {
    padding: 30px 24px;
    border-radius: 20px;
  }
}

/* Card Header titles */
.card-header {
  text-align: left;
}

.card-title {
  font-size: 1.7rem;
  font-weight: 800;
  color: var(--g900);
  letter-spacing: -0.03em;
  margin: 0;
  line-height: 1.15;
}

.card-subtitle {
  font-size: 0.8125rem;
  color: var(--g500);
  margin: 6px 0 0;
  font-weight: 500;
}

/* Forms layout controls */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--g600);
  letter-spacing: 0.01em;
}

/* Minimalist light-cream inputs */
.form-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--g200);
  border-radius: 10px;
  background: var(--g50);
  color: var(--g800);
  font-size: 0.875rem;
  font-family: inherit;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}

.form-input::placeholder {
  color: var(--g400);
}

.form-input:focus {
  background: #fff;
  border-color: var(--amber);
  box-shadow: 0 0 0 3px var(--amber-ring);
}

.input-error {
  border-color: var(--status-red) !important;
}

.form-error {
  font-size: 0.7rem;
  color: var(--status-red);
  margin-top: 4px;
  font-weight: 600;
}

/* Form Actions */
.forgot-link-neutral {
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--g500);
  cursor: help;
  text-decoration: underline;
}

.forgot-link-neutral:hover {
  color: var(--g800);
}

.btn-submit {
  padding: 12px 18px;
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0.01em;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

/* API Error Alert styles */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 0.78rem;
  font-weight: 600;
  box-sizing: border-box;
}

.alert--error {
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: var(--status-red);
}

.alert-icon {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}

/* Password eye toggle */
.relative {
  position: relative;
  width: 100%;
}

.pr-10 {
  padding-right: 40px;
}

.password-toggle-btn {
  position: absolute;
  top: 0;
  bottom: 0;
  right: 0;
  padding-right: 14px;
  display: flex;
  align-items: center;
  color: var(--g400);
  background: none;
  border: none;
  cursor: pointer;
  height: 100%;
  box-sizing: border-box;
}

.password-toggle-btn:hover {
  color: var(--g700);
}

.toggle-icon {
  width: 15px;
  height: 15px;
}

/* Card footer details */
.card-footer {
  display: flex;
  justify-content: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 500;
  margin-top: 4px;
}

.footer-subtext {
  color: var(--g500);
}

.footer-link {
  color: var(--g900);
  font-weight: 700;
  text-decoration: underline;
}

.footer-link:hover {
  color: var(--amber);
}

/* Loader Spinner */
.spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
