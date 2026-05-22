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
const pageTitle = computed(() => isEditMode.value ? 'Edit contract' : 'New contract')

const properties = ref<Property[]>([])
const tenants = ref<Tenant[]>([])
const dropdownsLoading = ref(false)

const form = reactive<StoreContractPayload>({
  tenant_id: '', property_id: '', start_date: '', end_date: '',
  monthly_rent: 0, deposit_amount: 0, billing_date: 1,
})

const fieldErrors = reactive<Partial<Record<keyof StoreContractPayload, string>>>({})

const billingDays = Array.from({ length: 28 }, (_, i) => i + 1)

const calculatedDuration = computed(() => {
  if (!form.start_date || !form.end_date) return null
  const start = new Date(form.start_date)
  const end = new Date(form.end_date)
  if (isNaN(start.getTime()) || isNaN(end.getTime())) return null
  if (end <= start) return null

  const months =
    (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
  
  if (months <= 0) return null
  return `Duration: ${months} ${months === 1 ? 'month' : 'months'}`
})

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
      start_date: form.start_date, end_date: form.end_date,
      monthly_rent: Number(form.monthly_rent), deposit_amount: Number(form.deposit_amount),
      billing_date: Number(form.billing_date),
    }
    await updateContract(route.params.id as string, updatePayload)
  } else {
    const createPayload: StoreContractPayload = {
      tenant_id: form.tenant_id, property_id: form.property_id,
      start_date: form.start_date, end_date: form.end_date,
      monthly_rent: Number(form.monthly_rent), deposit_amount: Number(form.deposit_amount),
      billing_date: Number(form.billing_date),
    }
    await createContract(createPayload)
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

    <div v-if="isLoading || dropdownsLoading" class="shimmer" style="height:380px;border-radius:16px" />

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <div class="card" style="margin-bottom:16px">
        <p class="section-label">Parties</p>
        <div class="form-grid" style="margin-top:12px">
          <div>
            <label class="form-label" for="tenant_id">Tenant <span style="color:#dc2626">*</span></label>
            <select id="tenant_id" v-model="form.tenant_id" class="form-select" :class="fieldErrors.tenant_id ? 'input--err' : ''" :disabled="isEditMode">
              <option value="">— Select tenant —</option>
              <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.email }})</option>
            </select>
            <p v-if="fieldErrors.tenant_id" class="form-error">{{ fieldErrors.tenant_id }}</p>
          </div>
          <div>
            <label class="form-label" for="property_id">Property <span style="color:#dc2626">*</span></label>
            <select id="property_id" v-model="form.property_id" class="form-select" :class="fieldErrors.property_id ? 'input--err' : ''" :disabled="isEditMode">
              <option value="">— Select property —</option>
              <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="fieldErrors.property_id" class="form-error">{{ fieldErrors.property_id }}</p>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <p class="section-label">Duration</p>
        <div class="duration-layout" style="margin-top:12px">
          <div class="duration-field">
            <label class="form-label" for="start_date">Start date <span style="color:#dc2626">*</span></label>
            <input id="start_date" v-model="form.start_date" type="date" class="form-input" :class="fieldErrors.start_date ? 'input--err' : ''" />
            <p v-if="fieldErrors.start_date" class="form-error">{{ fieldErrors.start_date }}</p>
          </div>

          <div class="duration-separator">
            <div v-if="calculatedDuration" class="duration-pill">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" />
              </svg>
              <span>{{ calculatedDuration }}</span>
            </div>
            <div v-else class="duration-line"></div>
          </div>

          <div class="duration-field">
            <label class="form-label" for="end_date">End date <span style="color:#dc2626">*</span></label>
            <input id="end_date" v-model="form.end_date" type="date" class="form-input" :class="fieldErrors.end_date ? 'input--err' : ''" />
            <p v-if="fieldErrors.end_date" class="form-error">{{ fieldErrors.end_date }}</p>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:24px">
        <p class="section-label">Financials</p>
        <div class="form-grid" style="margin-top:12px">
          <div>
            <label class="form-label" for="monthly_rent">Monthly rent (IDR) <span style="color:#dc2626">*</span></label>
            <div class="prefix-wrap">
              <span class="prefix">Rp</span>
              <input id="monthly_rent" v-model="form.monthly_rent" type="number" min="1" class="form-input prefix-input" :class="fieldErrors.monthly_rent ? 'input--err' : ''" placeholder="1500000" />
            </div>
            <p v-if="fieldErrors.monthly_rent" class="form-error">{{ fieldErrors.monthly_rent }}</p>
          </div>
          <div>
            <label class="form-label" for="deposit_amount">Deposit (IDR)</label>
            <div class="prefix-wrap">
              <span class="prefix">Rp</span>
              <input id="deposit_amount" v-model="form.deposit_amount" type="number" min="0" class="form-input prefix-input" :class="fieldErrors.deposit_amount ? 'input--err' : ''" placeholder="0" />
            </div>
            <p v-if="fieldErrors.deposit_amount" class="form-error">{{ fieldErrors.deposit_amount }}</p>
          </div>
          <div>
            <label class="form-label" for="billing_date">Billing day (1–28) <span style="color:#dc2626">*</span></label>
            <select id="billing_date" v-model="form.billing_date" class="form-select" :class="fieldErrors.billing_date ? 'input--err' : ''">
              <option v-for="day in billingDays" :key="day" :value="day">{{ day }}</option>
            </select>
            <p v-if="fieldErrors.billing_date" class="form-error">{{ fieldErrors.billing_date }}</p>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-primary" :disabled="isSubmitting">
          {{ isSubmitting ? 'Saving…' : isEditMode ? 'Update contract' : 'Create contract' }}
        </button>
        <button type="button" class="btn-ghost" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}
.input--err { border-color: #dc2626 !important; }
.prefix-wrap { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 12px; font-size: 0.875rem; color: var(--g500); font-weight: 600; pointer-events: none; }
.prefix-input { padding-left: 36px !important; }

/* Responsive Duration separator layout */
.duration-layout {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  width: 100%;
}

.duration-field {
  flex: 1;
}

.duration-separator {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  align-self: flex-start;
  min-width: 150px;
  height: 42px;
  margin-top: 26px; /* Align with inputs */
}

.duration-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: var(--amber-soft);
  color: var(--amber);
  border: 1px solid rgba(224, 156, 26, 0.25);
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
  box-shadow: 0 1px 2px rgba(224, 156, 26, 0.05);
}

.duration-pill svg {
  width: 14px;
  height: 14px;
  color: var(--amber);
}

.duration-line {
  width: 60px;
  height: 2px;
  background: var(--g200);
  position: relative;
}

.duration-line::after {
  content: '';
  position: absolute;
  right: 0;
  top: -3px;
  width: 8px;
  height: 8px;
  border-top: 2px solid var(--g300);
  border-right: 2px solid var(--g300);
  transform: rotate(45deg);
}

@media (max-width: 640px) {
  .duration-layout {
    flex-direction: column;
    gap: 12px;
  }
  .duration-separator {
    width: 100%;
    margin-top: 8px;
    margin-bottom: 8px;
    height: auto;
    align-self: center;
  }
  .duration-line {
    width: 100%;
    height: 1px;
  }
  .duration-line::after {
    display: none;
  }
}
</style>
