<script setup lang="ts">
import { onMounted, computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import type { StoreTenantPayload } from '@/types/tenant'

const route = useRoute()
const router = useRouter()
const { selectedTenant, isLoading, isSubmitting, error, fetchTenant, createTenant, updateTenant } = useTenant()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => isEditMode.value ? 'Edit tenant' : 'Add tenant')

const form = reactive<StoreTenantPayload>({
  name: '', email: '', phone: '', id_card_number: '',
  emergency_contact_name: '', emergency_contact_phone: '',
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
  if (!form.id_card_number.trim()) { fieldErrors.id_card_number = 'KTP number is required.'; valid = false }
  else if (!/^\d{16}$/.test(form.id_card_number)) { fieldErrors.id_card_number = 'KTP must be exactly 16 digits.'; valid = false }
  if (!form.emergency_contact_name.trim()) { fieldErrors.emergency_contact_name = 'Emergency contact name is required.'; valid = false }
  if (!form.emergency_contact_phone.trim()) { fieldErrors.emergency_contact_phone = 'Emergency contact phone is required.'; valid = false }
  return valid
}

async function handleSubmit() {
  if (!validate()) return
  const payload: StoreTenantPayload = {
    name: form.name.trim(), email: form.email.trim(), phone: form.phone.trim(),
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
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back
    </button>

    <div class="page-header" style="margin-bottom:20px">
      <h1 class="page-title">{{ pageTitle }}</h1>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="shimmer" style="height:320px;border-radius:16px" />

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <div class="card" style="margin-bottom:16px">
        <p class="section-label">Personal information</p>
        <div class="form-grid">
          <div>
            <label class="form-label" for="name">Full name <span style="color:#dc2626">*</span></label>
            <input id="name" v-model="form.name" type="text" class="form-input" :class="fieldErrors.name ? 'input--err' : ''" placeholder="e.g. Ani Wijaya" />
            <p v-if="fieldErrors.name" class="form-error">{{ fieldErrors.name }}</p>
          </div>
          <div>
            <label class="form-label" for="email">Email <span style="color:#dc2626">*</span></label>
            <input id="email" v-model="form.email" type="email" class="form-input" :class="fieldErrors.email ? 'input--err' : ''" placeholder="ani@example.com" />
            <p v-if="fieldErrors.email" class="form-error">{{ fieldErrors.email }}</p>
          </div>
          <div>
            <label class="form-label" for="phone">Phone <span style="color:#dc2626">*</span></label>
            <input id="phone" v-model="form.phone" type="tel" class="form-input" :class="fieldErrors.phone ? 'input--err' : ''" placeholder="081234567890" />
            <p v-if="fieldErrors.phone" class="form-error">{{ fieldErrors.phone }}</p>
          </div>
          <div>
            <label class="form-label" for="ktp">KTP number <span style="color:#dc2626">*</span></label>
            <input id="ktp" v-model="form.id_card_number" type="text" inputmode="numeric" maxlength="16" class="form-input" :class="fieldErrors.id_card_number ? 'input--err' : ''" placeholder="16-digit KTP number" style="font-family:monospace;letter-spacing:0.08em" />
            <p v-if="fieldErrors.id_card_number" class="form-error">{{ fieldErrors.id_card_number }}</p>
            <p style="font-size:0.7rem;color:var(--g400);margin-top:3px">{{ form.id_card_number.length }}/16 digits</p>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:24px">
        <p class="section-label">Emergency contact</p>
        <div class="form-grid">
          <div>
            <label class="form-label" for="ec-name">Contact name <span style="color:#dc2626">*</span></label>
            <input id="ec-name" v-model="form.emergency_contact_name" type="text" class="form-input" :class="fieldErrors.emergency_contact_name ? 'input--err' : ''" placeholder="e.g. Budi Wijaya" />
            <p v-if="fieldErrors.emergency_contact_name" class="form-error">{{ fieldErrors.emergency_contact_name }}</p>
          </div>
          <div>
            <label class="form-label" for="ec-phone">Contact phone <span style="color:#dc2626">*</span></label>
            <input id="ec-phone" v-model="form.emergency_contact_phone" type="tel" class="form-input" :class="fieldErrors.emergency_contact_phone ? 'input--err' : ''" placeholder="081234567890" />
            <p v-if="fieldErrors.emergency_contact_phone" class="form-error">{{ fieldErrors.emergency_contact_phone }}</p>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-primary" :disabled="isSubmitting">
          {{ isSubmitting ? 'Saving…' : isEditMode ? 'Update tenant' : 'Create tenant' }}
        </button>
        <button type="button" class="btn-cancel" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-top: 12px;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

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

.input--err { border-color: #dc2626 !important; }
</style>
