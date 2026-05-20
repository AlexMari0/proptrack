<script setup lang="ts">
import { onMounted, computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProperty } from '@/composables/useProperty'
import type { PropertyType, PropertyStatus, StorePropertyPayload } from '@/types/property'

const route = useRoute()
const router = useRouter()
const { selectedProperty, isLoading, isSubmitting, error, fetchProperty, createProperty, updateProperty } =
  useProperty()

const isEditMode = computed(() => !!route.params.id)
const pageTitle = computed(() => (isEditMode.value ? 'Edit Property' : 'Add Property'))

// ─── Form State ────────────────────────────────────────────────────────────────

const form = reactive<StorePropertyPayload>({
  name: '',
  address: '',
  type: 'kos',
  status: 'available',
  latitude: 0,
  longitude: 0,
  description: '',
  monthly_price: 0,
})

const errors = reactive<Partial<Record<keyof StorePropertyPayload, string>>>({})

// ─── Populate form on edit mode ────────────────────────────────────────────────

onMounted(async () => {
  if (isEditMode.value) {
    await fetchProperty(route.params.id as string)
  }
})

watch(selectedProperty, (property) => {
  if (property && isEditMode.value) {
    form.name = property.name
    form.address = property.address
    form.type = property.type
    form.status = property.status
    form.latitude = property.latitude
    form.longitude = property.longitude
    form.description = property.description ?? ''
    form.monthly_price = property.monthly_price
  }
})

// ─── Validation ────────────────────────────────────────────────────────────────

function validate(): boolean {
  // Clear previous errors
  Object.keys(errors).forEach((k) => delete errors[k as keyof StorePropertyPayload])

  let valid = true

  if (!form.name.trim()) { errors.name = 'Name is required.'; valid = false }
  if (!form.address.trim()) { errors.address = 'Address is required.'; valid = false }
  if (!form.type) { errors.type = 'Type is required.'; valid = false }
  if (!form.status) { errors.status = 'Status is required.'; valid = false }
  if (form.latitude < -90 || form.latitude > 90) { errors.latitude = 'Latitude must be between -90 and 90.'; valid = false }
  if (form.longitude < -180 || form.longitude > 180) { errors.longitude = 'Longitude must be between -180 and 180.'; valid = false }
  if (form.monthly_price < 0) { errors.monthly_price = 'Price must be 0 or greater.'; valid = false }

  return valid
}

// ─── Submit ────────────────────────────────────────────────────────────────────

async function handleSubmit() {
  if (!validate()) return

  const payload: StorePropertyPayload = {
    name: form.name.trim(),
    address: form.address.trim(),
    type: form.type,
    status: form.status,
    latitude: Number(form.latitude),
    longitude: Number(form.longitude),
    description: form.description?.trim() || undefined,
    monthly_price: Number(form.monthly_price),
  }

  if (isEditMode.value) {
    await updateProperty(route.params.id as string, payload)
  } else {
    await createProperty(payload)
  }
}

const typeOptions: { label: string; value: PropertyType }[] = [
  { label: 'Kos', value: 'kos' },
  { label: 'Apartment', value: 'apartment' },
  { label: 'Ruko', value: 'ruko' },
]

const statusOptions: { label: string; value: PropertyStatus }[] = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' },
]
</script>

<template>
  <div class="page">
    <!-- Back -->
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back
    </button>

    <div class="form-card">
      <h1 class="form-card__title">{{ pageTitle }}</h1>

      <!-- Loading (edit mode fetching property) -->
      <div v-if="isLoading" class="form-loading">
        <span class="spinner" />
        <span>Loading property data…</span>
      </div>

      <!-- Server-side error -->
      <div v-if="error" class="alert alert--error">{{ error }}</div>

      <form v-if="!isLoading" @submit.prevent="handleSubmit" novalidate>

        <!-- Name -->
        <div class="field">
          <label class="field__label" for="name">Property Name <span class="field__required">*</span></label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="field__input"
            :class="{ 'field__input--error': errors.name }"
            placeholder="e.g. Kos Harmoni"
          />
          <span v-if="errors.name" class="field__error">{{ errors.name }}</span>
        </div>

        <!-- Address -->
        <div class="field">
          <label class="field__label" for="address">Address <span class="field__required">*</span></label>
          <textarea
            id="address"
            v-model="form.address"
            class="field__input field__textarea"
            :class="{ 'field__input--error': errors.address }"
            placeholder="e.g. Jl. Harmoni No. 12, Jakarta Pusat"
            rows="2"
          />
          <span v-if="errors.address" class="field__error">{{ errors.address }}</span>
        </div>

        <!-- Type & Status row -->
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="type">Type <span class="field__required">*</span></label>
            <select
              id="type"
              v-model="form.type"
              class="field__input field__select"
              :class="{ 'field__input--error': errors.type }"
            >
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <span v-if="errors.type" class="field__error">{{ errors.type }}</span>
          </div>

          <div class="field">
            <label class="field__label" for="status">Status <span class="field__required">*</span></label>
            <select
              id="status"
              v-model="form.status"
              class="field__input field__select"
              :class="{ 'field__input--error': errors.status }"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <span v-if="errors.status" class="field__error">{{ errors.status }}</span>
          </div>
        </div>

        <!-- Latitude & Longitude row -->
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="latitude">Latitude <span class="field__required">*</span></label>
            <input
              id="latitude"
              v-model="form.latitude"
              type="number"
              step="any"
              class="field__input"
              :class="{ 'field__input--error': errors.latitude }"
              placeholder="-6.1751"
            />
            <span v-if="errors.latitude" class="field__error">{{ errors.latitude }}</span>
          </div>

          <div class="field">
            <label class="field__label" for="longitude">Longitude <span class="field__required">*</span></label>
            <input
              id="longitude"
              v-model="form.longitude"
              type="number"
              step="any"
              class="field__input"
              :class="{ 'field__input--error': errors.longitude }"
              placeholder="106.8272"
            />
            <span v-if="errors.longitude" class="field__error">{{ errors.longitude }}</span>
          </div>
        </div>

        <!-- Monthly Price -->
        <div class="field">
          <label class="field__label" for="monthly_price">Monthly Price (IDR) <span class="field__required">*</span></label>
          <div class="field__prefix-group">
            <span class="field__prefix">Rp</span>
            <input
              id="monthly_price"
              v-model="form.monthly_price"
              type="number"
              min="0"
              class="field__input field__input--prefixed"
              :class="{ 'field__input--error': errors.monthly_price }"
              placeholder="1500000"
            />
          </div>
          <span v-if="errors.monthly_price" class="field__error">{{ errors.monthly_price }}</span>
        </div>

        <!-- Description -->
        <div class="field">
          <label class="field__label" for="description">Description <span class="field__optional">(optional)</span></label>
          <textarea
            id="description"
            v-model="form.description"
            class="field__input field__textarea"
            placeholder="Describe the property, facilities, and location highlights…"
            rows="4"
          />
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <button type="button" class="btn btn--ghost" @click="router.back()">Cancel</button>
          <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner" />
            {{ isEditMode ? 'Save Changes' : 'Create Property' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 760px; margin: 0 auto; }

.back-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: none; border: none; color: var(--color-text-muted);
  font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 24px; transition: color 0.2s;
}
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

/* Card */
.form-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 20px;
  padding: 32px;
}

.form-card__title { font-size: 1.4rem; font-weight: 700; color: var(--color-text); margin: 0 0 28px; }

/* Loading */
.form-loading { display: flex; align-items: center; gap: 12px; color: var(--color-text-muted); padding: 32px 0; }

/* Fields */
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }

.field__label {
  font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);
  text-transform: uppercase; letter-spacing: 0.05em;
}
.field__required { color: #ef4444; }
.field__optional { font-weight: 400; text-transform: none; letter-spacing: 0; }

.field__input {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-alt);
  color: var(--color-text);
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}

.field__input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.field__input--error { border-color: #ef4444; }
.field__input--error:focus { box-shadow: 0 0 0 3px rgba(239,68,68,0.12); }

.field__textarea { resize: vertical; min-height: 72px; }
.field__select { cursor: pointer; }

/* Prefix group */
.field__prefix-group { position: relative; display: flex; align-items: center; }
.field__prefix {
  position: absolute; left: 14px;
  font-size: 0.875rem; font-weight: 600;
  color: var(--color-text-muted); pointer-events: none;
}
.field__input--prefixed { padding-left: 38px; }

.field__error { font-size: 0.78rem; color: #ef4444; }

/* Two-column layout */
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 520px) { .field-row { grid-template-columns: 1fr; } }

/* Actions */
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; padding-top: 24px; border-top: 1px solid var(--color-border); }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: 10px;
  font-size: 0.875rem; font-weight: 600; cursor: pointer;
  border: none; text-decoration: none; transition: all 0.2s;
}
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Spinner */
.spinner {
  width: 16px; height: 16px;
  border: 2px solid currentColor; border-top-color: transparent;
  border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
