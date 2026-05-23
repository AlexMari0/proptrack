<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'

const { authStore, updateProfile, loading, error: apiError } = useAuth()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const successMessage = ref('')
const formErrors = ref({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
})

const isTenant = computed(() => authStore.user?.roles?.includes('tenant'))
const userRoleLabel = computed(() => {
  const roles = authStore.user?.roles || []
  if (roles.includes('admin')) return 'Administrator'
  if (roles.includes('owner')) return 'Property Owner'
  if (roles.includes('agent')) return 'Support Agent'
  if (roles.includes('tenant')) return 'Tenant Resident'
  return 'User'
})

onMounted(() => {
  if (authStore.user) {
    name.value = authStore.user.name
    email.value = authStore.user.email
  }
})

const validateInfo = () => {
  let isValid = true
  formErrors.value = { name: '', email: '', password: '', passwordConfirmation: '' }
  successMessage.value = ''

  if (!name.value.trim()) {
    formErrors.value.name = 'Full name is required'
    isValid = false
  }

  if (!email.value.trim()) {
    formErrors.value.email = 'Email address is required'
    isValid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    formErrors.value.email = 'Please enter a valid email address'
    isValid = false
  }

  return isValid
}

const validatePassword = () => {
  let isValid = true
  formErrors.value = { name: '', email: '', password: '', passwordConfirmation: '' }
  successMessage.value = ''

  if (!password.value) {
    formErrors.value.password = 'New password is required'
    isValid = false
  } else if (password.value.length < 8) {
    formErrors.value.password = 'Password must be at least 8 characters'
    isValid = false
  }

  if (password.value !== passwordConfirmation.value) {
    formErrors.value.passwordConfirmation = 'Passwords do not match'
    isValid = false
  }

  return isValid
}

const onSubmitInfo = async () => {
  if (!validateInfo()) return

  try {
    await updateProfile({
      name: name.value,
      email: email.value,
    })
    successMessage.value = 'Personal information updated successfully!'
    setTimeout(() => {
      successMessage.value = ''
    }, 4000)
  } catch (err) {
    console.error('Failed to update personal details:', err)
  }
}

const onSubmitPassword = async () => {
  if (!validatePassword()) return

  try {
    await updateProfile({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    successMessage.value = 'Password changed successfully!'
    password.value = ''
    passwordConfirmation.value = ''
    showPassword.value = false
    showPasswordConfirmation.value = false
    setTimeout(() => {
      successMessage.value = ''
    }, 4000)
  } catch (err) {
    console.error('Failed to change password:', err)
  }
}
</script>

<template>
  <div class="profile-settings">
    <!-- Top Greeting Section -->
    <header class="profile-header">
      <h1 class="profile-title">Profile Settings</h1>
      <p class="profile-subtitle">Manage your personal account details, credentials, and settings.</p>
    </header>

    <div class="profile-grid">
      <!-- ═══ Main Card: User Badge & Config panels ═══ -->
      <section class="profile-main-card">
        <!-- User Badge Header -->
        <div class="user-badge-banner">
          <div class="profile-avatar">
            {{ name?.[0]?.toUpperCase() || 'U' }}
          </div>
          <div class="user-badge-meta">
            <h2 class="user-badge-name">{{ name }}</h2>
            <div class="badge-row">
              <span class="role-badge" :class="`role-badge--${authStore.user?.roles?.[0]}`">
                {{ userRoleLabel }}
              </span>
              <span class="user-badge-email">{{ authStore.user?.email }}</span>
            </div>
          </div>
        </div>

        <!-- Global Success Alert -->
        <Transition name="fade">
          <div v-if="successMessage" class="alert alert--success" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="alert-icon">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            <span>{{ successMessage }}</span>
          </div>
        </Transition>

        <!-- Global API error alert -->
        <Transition name="fade">
          <div v-if="apiError" class="alert alert--error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="alert-icon">
              <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ apiError }}</span>
          </div>
        </Transition>

        <!-- Form 1: Personal Information -->
        <form @submit.prevent="onSubmitInfo" class="settings-form">
          <div class="form-section-header">Personal Information</div>
          
          <div class="form-group-row">
            <!-- Full Name input -->
            <div class="form-group">
              <label for="profile-name" class="form-label">Full Name</label>
              <input
                id="profile-name"
                v-model="name"
                type="text"
                placeholder="Your Full Name"
                :disabled="loading"
                class="form-input"
                :class="{ 'input-error': formErrors.name }"
              />
              <span v-if="formErrors.name" class="form-error">{{ formErrors.name }}</span>
            </div>

            <!-- Email Address input -->
            <div class="form-group">
              <label for="profile-email" class="form-label">Email Address</label>
              <input
                id="profile-email"
                v-model="email"
                type="email"
                placeholder="your.email@example.com"
                :disabled="loading"
                class="form-input"
                :class="{ 'input-error': formErrors.email }"
              />
              <span v-if="formErrors.email" class="form-error">{{ formErrors.email }}</span>
            </div>
          </div>

          <!-- Synchronize Warning Badge -->
          <div class="sync-tip-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="tip-icon">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            <p v-if="isTenant" class="tip-text">
              <strong>Notice:</strong> Changing your email address will update your login credentials and automatically synchronize your tenant lease history.
            </p>
            <p v-else class="tip-text">
              <strong>Notice:</strong> Modifying your email address changes your login credentials. You will need to use your new email for subsequent sign-ins.
            </p>
          </div>

          <div class="action-footer">
            <button
              type="submit"
              :disabled="loading"
              class="btn-primary btn-save"
            >
              <span v-if="loading" class="spinner-sm" />
              {{ loading ? 'Saving Info...' : 'Save Info' }}
            </button>
          </div>
        </form>

        <hr class="form-separator" />

        <!-- Form 2: Change Password -->
        <form @submit.prevent="onSubmitPassword" class="settings-form">
          <div class="form-section-header">Change Password</div>
          <p class="section-subtext">Update your account password. Passwords must be at least 8 characters long.</p>

          <div class="form-group-row">
            <!-- New Password -->
            <div class="form-group">
              <label for="profile-password" class="form-label">New Password</label>
              <div class="relative">
                <input
                  id="profile-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Minimum 8 characters"
                  :disabled="loading"
                  class="form-input pr-10"
                  :class="{ 'input-error': formErrors.password }"
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
              <span v-if="formErrors.password" class="form-error">{{ formErrors.password }}</span>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label for="profile-password-confirm" class="form-label">Confirm New Password</label>
              <div class="relative">
                <input
                  id="profile-password-confirm"
                  v-model="passwordConfirmation"
                  :type="showPasswordConfirmation ? 'text' : 'password'"
                  placeholder="Confirm password"
                  :disabled="loading"
                  class="form-input pr-10"
                  :class="{ 'input-error': formErrors.passwordConfirmation }"
                />
                <button 
                  type="button"
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                  class="password-toggle-btn"
                  :disabled="loading"
                  aria-label="Toggle confirm password visibility"
                >
                  <svg v-if="showPasswordConfirmation" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="toggle-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815 3.183 3.183m-3.183-3.183-2.14-2.14m-2.533-2.533-3.854-3.854m0 0a3 3 0 1 0 4.243 4.242" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="toggle-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                  </svg>
                </button>
              </div>
              <span v-if="formErrors.passwordConfirmation" class="form-error">{{ formErrors.passwordConfirmation }}</span>
            </div>
          </div>

          <div class="action-footer">
            <button
              type="submit"
              :disabled="loading"
              class="btn-primary btn-save"
            >
              <span v-if="loading" class="spinner-sm" />
              {{ loading ? 'Updating...' : 'Update Password' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>

<style scoped>
.profile-settings {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px 32px;
  max-width: 900px;
  margin: 0 auto;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .profile-settings {
    padding: 16px;
    gap: 16px;
  }
}

.profile-header {
  margin-bottom: 4px;
}

.profile-title {
  font-size: 1.55rem;
  font-weight: 800;
  color: var(--g900);
  letter-spacing: -0.03em;
  margin: 0;
  line-height: 1.25;
}

.profile-subtitle {
  font-size: 0.8125rem;
  color: var(--g500);
  margin: 5px 0 0;
}

.profile-grid {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.profile-main-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--g100);
  padding: 28px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
}

@media (max-width: 600px) {
  .profile-main-card {
    padding: 20px;
  }
}

/* Badge Banner */
.user-badge-banner {
  display: flex;
  align-items: center;
  gap: 20px;
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

@media (max-width: 500px) {
  .user-badge-banner {
    flex-direction: column;
    text-align: center;
    padding: 20px 16px;
    gap: 12px;
  }
}

.profile-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--g900);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
}

.user-badge-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.user-badge-name {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

@media (max-width: 500px) {
  .badge-row {
    justify-content: center;
  }
}

.user-badge-email {
  font-size: 0.78rem;
  color: var(--g500);
  font-weight: 500;
}

.role-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.role-badge--admin {
  background: rgba(79, 70, 229, 0.1);
  color: #4f46e5;
}

.role-badge--owner {
  background: rgba(224, 156, 26, 0.1);
  color: #92640a;
}

.role-badge--agent {
  background: rgba(59, 130, 246, 0.1);
  color: #1d4ed8;
}

.role-badge--tenant {
  background: rgba(34, 197, 94, 0.1);
  color: #15803d;
}

/* Forms layout */
.settings-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section-header {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--g900);
  letter-spacing: -0.01em;
  margin-top: 4px;
}

.section-subtext {
  font-size: 0.75rem;
  color: var(--g400);
  margin: -14px 0 4px;
  line-height: 1.4;
}

.form-group-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (max-width: 600px) {
  .form-group-row {
    grid-template-columns: 1fr;
    gap: 14px;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-separator {
  border: 0;
  border-top: 1px dashed var(--g200);
  margin: 10px 0;
}

/* Notifications alerts */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 0.78rem;
  font-weight: 600;
  margin-bottom: 20px;
  box-sizing: border-box;
}

.alert--success {
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.2);
  color: #15803d;
}

.alert--error {
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #b91c1c;
}

.alert-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.sync-tip-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: rgba(224, 156, 26, 0.04);
  border: 1px dashed rgba(224, 156, 26, 0.2);
  border-radius: 10px;
  padding: 12px 16px;
  margin-top: 4px;
}

.tip-icon {
  width: 14px;
  height: 14px;
  color: var(--amber);
  margin-top: 2px;
  flex-shrink: 0;
}

.tip-text {
  font-size: 0.7rem;
  color: #92640a;
  line-height: 1.45;
  margin: 0;
}

.action-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 8px;
}

.btn-save {
  min-width: 120px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-weight: 700;
}

.spinner-sm {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
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

/* Password Toggle Elements */
.relative {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
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
}

.password-toggle-btn:hover {
  color: var(--g600);
}

.toggle-icon {
  width: 16px;
  height: 16px;
}
</style>
