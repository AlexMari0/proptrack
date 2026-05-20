<script setup lang="ts">
import { onMounted, computed, reactive, watch, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContract } from '@/composables/useContract'
import { propertyService } from '@/services/propertyService'
import { tenantService } from '@/services/tenantService'
import type { StoreContractPayload, UpdateContractPayload } from '@/types/contract'
import type { Property } from '@/types/property'
import type { Tenant } from '@/types/tenant'

const route = useRoute()
const router = useRouter()
const { selectedContract, isLoading, isSubmitting, error, fetchContract, createContract, updateContract } = useContract()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => isEditMode.value ? 'Edit Contract' : 'New Contract')

// Dropdown data
const properties = ref<Property[]>([])
const tenants = ref<Tenant[]>([])
const dropdownsLoading = ref(false)

const form = reactive<StoreContractPayload>({
  tenant_id: '',
  property_id: '',
  start_date: '',
  end_date: '',
  monthly_rent: 0,
  deposit_amount: 0,
  billing_date: 1,
})

const fieldErrors = reactive<Partial<Record<keyof StoreContractPayload, string>>>({})

// Load dropdown options
async function loadDropdowns() {
  dropdownsLoading.value = true
  try {
    const [propRes, tenantRes] = await Promise.all([
      propertyService.list({ per_page: 100 }),
      tenantService.list({ per_page: 100 }),
    ])
    properties.value = propRes.data
    tenants.value = tenantRes.data
  } finally {
    dropdownsLoading.value = false
  }
}

onMounted(async () => {
  await loadDropdowns()
  if (isEditMode.value) await fetchContract(route.params.id as string)
})

watch(selectedContract, (contract) => {
  if (contract && isEditMode.value) {
    form.tenant_id = contract.tenant.id
    form.property_id = contract.property.id
    form.start_date = contract.start_date
    form.end_date = contract.end_date
    form.monthly_rent = contract.monthly_rent
    form.deposit_amount = contract.deposit_amount
    form.billing_date = contract.billing_date
  }
})

function validate(): boolean {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k as keyof StoreContractPayload])
  let valid = true

  if (!form.tenant_id) { fieldErrors.tenant_id = 'Please select a tenant.'; valid = false }
  if (!form.property_id) { fieldErrors.property_id = 'Please select a property.'; valid = false }
  if (!form.start_date) { fieldErrors.start_date = 'Start date is required.'; valid = false }
  if (!form.end_date) { fieldErrors.end_date = 'End date is required.'; valid = false }
  else if (form.end_date <= form.start_date) { fieldErrors.end_date = 'End date must be after start date.'; valid = false }
  if (form.monthly_rent < 1) { fieldErrors.monthly_rent = 'Monthly rent must be at least 1.'; valid = false }
  if (form.deposit_amount < 0) { fieldErrors.deposit_amount = 'Deposit cannot be negative.'; valid = false }
  if (form.billing_date < 1 || form.billing_date > 28) { fieldErrors.billing_date = 'Billing date must be between 1 and 28.'; valid = false }

  return valid
}

async function handleSubmit() {
  if (!validate()) return

  if (isEditMode.value) {
    const updatePayload: UpdateContractPayload = {
      start_date: form.start_date,
      end_date: form.end_date,
      monthly_rent: Number(form.monthly_rent),
      deposit_amount: Number(form.deposit_amount),
      billing_date: Number(form.billing_date),
    }
    await updateContract(route.params.id as string, updatePayload)
  } else {
    const createPayload: StoreContractPayload = {
      tenant_id: form.tenant_id,
      property_id: form.property_id,
      start_date: form.start_date,
      end_date: form.end_date,
      monthly_rent: Number(form.monthly_rent),
      deposit_amount: Number(form.deposit_amount),
      billing_date: Number(form.billing_date),
    }
    await createContract(createPayload)
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

      <div v-if="isLoading || dropdownsLoading" class="form-loading">
        <span class="spinner" /> Loading…
      </div>

      <div v-if="error" class="alert alert--error">{{ error }}</div>

      <form v-if="!isLoading && !dropdownsLoading" @submit.prevent="handleSubmit" novalidate>
        <p class="section-label">Parties</p>

        <!-- Tenant dropdown -->
        <div class="field">
          <label class="field__label" for="tenant_id">Tenant <span class="req">*</span></label>
          <select id="tenant_id" v-model="form.tenant_id" class="field__input" :class="{ 'field__input--error': fieldErrors.tenant_id }" :disabled="isEditMode">
            <option value="">— Select tenant —</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.email }})</option>
          </select>
          <span v-if="fieldErrors.tenant_id" class="field__error">{{ fieldErrors.tenant_id }}</span>
        </div>

        <!-- Property dropdown -->
        <div class="field">
          <label class="field__label" for="property_id">Property <span class="req">*</span></label>
          <select id="property_id" v-model="form.property_id" class="field__input" :class="{ 'field__input--error': fieldErrors.property_id }" :disabled="isEditMode">
            <option value="">— Select property —</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <span v-if="fieldErrors.property_id" class="field__error">{{ fieldErrors.property_id }}</span>
        </div>

        <p class="section-label">Duration</p>

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="start_date">Start Date <span class="req">*</span></label>
            <input id="start_date" v-model="form.start_date" type="date" class="field__input" :class="{ 'field__input--error': fieldErrors.start_date }" />
            <span v-if="fieldErrors.start_date" class="field__error">{{ fieldErrors.start_date }}</span>
          </div>
          <div class="field">
            <label class="field__label" for="end_date">End Date <span class="req">*</span></label>
            <input id="end_date" v-model="form.end_date" type="date" class="field__input" :class="{ 'field__input--error': fieldErrors.end_date }" />
            <span v-if="fieldErrors.end_date" class="field__error">{{ fieldErrors.end_date }}</span>
          </div>
        </div>

        <p class="section-label">Financials</p>

        <div class="field-row">
          <div class="field">
            <label class="field__label" for="monthly_rent">Monthly Rent (IDR) <span class="req">*</span></label>
            <div class="field__prefix-wrap">
              <span class="field__prefix">Rp</span>
              <input id="monthly_rent" v-model="form.monthly_rent" type="number" min="1" class="field__input field__input--prefixed" :class="{ 'field__input--error': fieldErrors.monthly_rent }" placeholder="1500000" />
            </div>
            <span v-if="fieldErrors.monthly_rent" class="field__error">{{ fieldErrors.monthly_rent }}</span>
          </div>
          <div class="field">
            <label class="field__label" for="deposit_amount">Deposit (IDR) <span class="req">*</span></label>
            <div class="field__prefix-wrap">
              <span class="field__prefix">Rp</span>
              <input id="deposit_amount" v-model="form.deposit_amount" type="number" min="0" class="field__input field__input--prefixed" :class="{ 'field__input--error': fieldErrors.deposit_amount }" placeholder="3000000" />
            </div>
            <span v-if="fieldErrors.deposit_amount" class="field__error">{{ fieldErrors.deposit_amount }}</span>
          </div>
        </div>

        <div class="field" style="max-width: 200px;">
          <label class="field__label" for="billing_date">
            Billing Day <span class="req">*</span>
            <span class="field__hint">(1–28)</span>
          </label>
          <input id="billing_date" v-model="form.billing_date" type="number" min="1" max="28" class="field__input" :class="{ 'field__input--error': fieldErrors.billing_date }" />
          <span v-if="fieldErrors.billing_date" class="field__error">{{ fieldErrors.billing_date }}</span>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" @click="router.back()">Cancel</button>
          <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner" />
            {{ isEditMode ? 'Save Changes' : 'Create Contract' }}
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
.section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--color-primary); margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--color-border); }
.form-loading { display: flex; align-items: center; gap: 12px; color: var(--color-text-muted); padding: 32px 0; }

.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 18px; }
.field__label { font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px; }
.field__hint { font-size: 0.72rem; font-weight: 400; text-transform: none; letter-spacing: 0; opacity: 0.7; }
.req { color: #ef4444; }

.field__input { padding: 10px 14px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface-alt); color: var(--color-text); font-size: 0.9rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.field__input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.field__input--error { border-color: #ef4444; }
.field__input:disabled { opacity: 0.5; cursor: not-allowed; }
.field__prefix-wrap { position: relative; display: flex; align-items: center; }
.field__prefix { position: absolute; left: 14px; font-size: 0.875rem; font-weight: 600; color: var(--color-text-muted); pointer-events: none; }
.field__input--prefixed { padding-left: 38px; }
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
