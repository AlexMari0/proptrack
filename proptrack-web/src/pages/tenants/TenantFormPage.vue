<script setup lang="ts">
import { onMounted, computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import type { StoreTenantPayload } from '@/types/tenant'

const route = useRoute()
const router = useRouter()
const { selectedTenant, isLoading, isSubmitting, error, fetchTenant, createTenant, updateTenant } = useTenant()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => isEditMode.value ? 'Edit Tenant' : 'Add Tenant')

const form = reactive<StoreTenantPayload>({
  name: '',
  email: '',
  phone: '',
  id_card_number: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
})

const fieldErrors = reactive<Partial<Record<keyof StoreTenantPayload, string>>>({})

onMounted(async () => {
  if (isEditMode.value) await fetchTenant(route.params.id as string)
})

watch(selectedTenant, (tenant) => {
  if (tenant && isEditMode.value) {
    form.name = tenant.name
    form.email = tenant.email
    form.phone = tenant.phone
    form.id_card_number = tenant.id_card_number
    form.emergency_contact_name = tenant.emergency_contact_name
    form.emergency_contact_phone = tenant.emergency_contact_phone
  }
})

function validate(): boolean {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k as keyof StoreTenantPayload])
  let valid = true

  if (!form.name.trim()) { fieldErrors.name = 'Name is required.'; valid = false }
  if (!form.email.trim()) { fieldErrors.email = 'Email is required.'; valid = false }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { fieldErrors.email = 'Enter a valid email address.'; valid = false }
  if (!form.phone.trim()) { fieldErrors.phone = 'Phone number is required.'; valid = false }
  if (!form.id_card_number.trim()) {
    fieldErrors.id_card_number = 'KTP number is required.'
    valid = false
  } else if (!/^\d{16}$/.test(form.id_card_number)) {
    fieldErrors.id_card_number = 'KTP must be exactly 16 digits.'
    valid = false
  }
  if (!form.emergency_contact_name.trim()) { fieldErrors.emergency_contact_name = 'Emergency contact name is required.'; valid = false }
  if (!form.emergency_contact_phone.trim()) { fieldErrors.emergency_contact_phone = 'Emergency contact phone is required.'; valid = false }

  return valid
}

async function handleSubmit() {
  if (!validate()) return

  const payload: StoreTenantPayload = {
    name: form.name.trim(),
    email: form.email.trim(),
    phone: form.phone.trim(),
    id_card_number: form.id_card_number.trim(),
    emergency_contact_name: form.emergency_contact_name.trim(),
    emergency_contact_phone: form.emergency_contact_phone.trim(),
  }

  if (isEditMode.value) {
    await updateTenant(route.params.id as string, payload)
  } else {
    await createTenant(payload)
  }
}
</script>

<template>
  <div class="page">
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back
    </button>

    <div class="form-card">
      <h1 class="form-card__title">{{ pageTitle }}</h1>

      <div v-if="isLoading" class="form-loading">
        <span class="spinner" /> Loading tenant data…
      </div>

      <div v-if="error" class="alert alert--error">{{ error }}</div>

      <form v-if="!isLoading" @submit.prevent="handleSubmit" novalidate>

        <!-- Personal info -->
        <p class="form-section-label">Personal Information</p>

        <div class="field">
          <label class="field__label" for="name">Full Name <span class="req">*</span></label>
          <input id="name" v-model="form.name" type="text" class="field__input" :class="{ 'field__input--error': fieldErrors.name }" placeholder="e.g. Ani Wijaya" />
          <span v-if="fieldErrors.name" class="field__error">{{ fieldErrors.name }}</span>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="email">Email <span class="req">*</span></label>
            <input id="email" v-model="form.email" type="email" class="field__input" :class="{ 'field__input--error': fieldErrors.email }" placeholder="ani@example.com" />
            <span v-if="fieldErrors.email" class="field__error">{{ fieldErrors.email }}</span>
          </div>
          <div class="field">
            <label class="field__label" for="phone">Phone <span class="req">*</span></label>
            <input id="phone" v-model="form.phone" type="tel" class="field__input" :class="{ 'field__input--error': fieldErrors.phone }" placeholder="081234567890" />
            <span v-if="fieldErrors.phone" class="field__error">{{ fieldErrors.phone }}</span>
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="id_card_number">
            KTP Number <span class="req">*</span>
            <span class="field__hint">Must be exactly 16 digits</span>
          </label>
          <input id="id_card_number" v-model="form.id_card_number" type="text" inputmode="numeric" maxlength="16" class="field__input field__input--mono" :class="{ 'field__input--error': fieldErrors.id_card_number }" placeholder="3171234567890001" />
          <div class="field__counter">{{ form.id_card_number.length }}/16</div>
          <span v-if="fieldErrors.id_card_number" class="field__error">{{ fieldErrors.id_card_number }}</span>
        </div>

        <!-- Emergency contact -->
        <p class="form-section-label" style="margin-top: 8px;">Emergency Contact</p>

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="emergency_contact_name">Name <span class="req">*</span></label>
            <input id="emergency_contact_name" v-model="form.emergency_contact_name" type="text" class="field__input" :class="{ 'field__input--error': fieldErrors.emergency_contact_name }" placeholder="e.g. Ibu Sari" />
            <span v-if="fieldErrors.emergency_contact_name" class="field__error">{{ fieldErrors.emergency_contact_name }}</span>
          </div>
          <div class="field">
            <label class="field__label" for="emergency_contact_phone">Phone <span class="req">*</span></label>
            <input id="emergency_contact_phone" v-model="form.emergency_contact_phone" type="tel" class="field__input" :class="{ 'field__input--error': fieldErrors.emergency_contact_phone }" placeholder="081298765432" />
            <span v-if="fieldErrors.emergency_contact_phone" class="field__error">{{ fieldErrors.emergency_contact_phone }}</span>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" @click="router.back()">Cancel</button>
          <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner" />
            {{ isEditMode ? 'Save Changes' : 'Add Tenant' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 720px; margin: 0 auto; }
.back-btn { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; color: var(--color-text-muted); font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 24px; transition: color 0.2s; }
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

.form-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 20px; padding: 32px; }
.form-card__title { font-size: 1.4rem; font-weight: 700; color: var(--color-text); margin: 0 0 24px; }
.form-section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--color-primary); margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--color-border); }
.form-loading { display: flex; align-items: center; gap: 12px; color: var(--color-text-muted); padding: 32px 0; }

.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 18px; }
.field__label { font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px; }
.field__hint { font-size: 0.72rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--color-text-muted); opacity: 0.7; }
.req { color: #ef4444; }

.field__input { padding: 10px 14px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface-alt); color: var(--color-text); font-size: 0.9rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.field__input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.field__input--error { border-color: #ef4444; }
.field__input--error:focus { box-shadow: 0 0 0 3px rgba(239,68,68,0.12); }
.field__input--mono { font-family: monospace; letter-spacing: 0.1em; font-size: 1rem; }
.field__counter { font-size: 0.72rem; color: var(--color-text-muted); text-align: right; margin-top: 2px; }
.field__error { font-size: 0.78rem; color: #ef4444; }

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 520px) { .field-row { grid-template-columns: 1fr; } }

.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; padding-top: 24px; border-top: 1px solid var(--color-border); }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
