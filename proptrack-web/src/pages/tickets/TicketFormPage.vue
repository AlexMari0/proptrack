<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicket } from '@/composables/useTicket'
import { propertyService } from '@/services/propertyService'
import type { CreateTicketPayload } from '@/types/ticket'
import type { Property } from '@/types/property'

const router = useRouter()
const { createTicket, isSubmitting, error } = useTicket()

const properties = ref<Property[]>([])
const propertiesLoading = ref(false)

const form = reactive<CreateTicketPayload>({
  property_id: '', category: 'maintenance', priority: 'medium', title: '', description: '',
})

const fieldErrors = reactive<Partial<Record<keyof CreateTicketPayload, string>>>({})

async function loadProperties() {
  propertiesLoading.value = true
  try {
    const response = await propertyService.list({ per_page: 100 })
    properties.value = response.data
  } catch (err) {
    console.error('Failed to load properties for ticket creation:', err)
  } finally {
    propertiesLoading.value = false
  }
}

onMounted(async () => { await loadProperties() })

function validate(): boolean {
  Object.keys(fieldErrors).forEach((key) => { delete fieldErrors[key as keyof CreateTicketPayload] })
  let isValid = true
  if (!form.property_id) { fieldErrors.property_id = 'Please select a property.'; isValid = false }
  if (!form.title.trim()) { fieldErrors.title = 'Ticket title is required.'; isValid = false }
  else if (form.title.length > 255) { fieldErrors.title = 'Title cannot exceed 255 characters.'; isValid = false }
  if (!form.description.trim()) { fieldErrors.description = 'Description is required.'; isValid = false }
  return isValid
}

async function handleSubmit() {
  if (!validate()) return
  await createTicket({
    property_id: form.property_id, category: form.category, priority: form.priority,
    title: form.title.trim(), description: form.description.trim(),
  })
}
</script>

<template>
  <div class="page-content">
    <button class="back-link" @click="router.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      Back
    </button>

    <div class="page-header" style="margin-bottom:4px">
      <h1 class="page-title">Submit a ticket</h1>
    </div>
    <p class="page-subtitle" style="margin-bottom:24px">Describe your issue in detail so the management team can respond promptly.</p>

    <div v-if="error" class="alert alert--error">{{ error }}</div>
    <div v-if="propertiesLoading" class="shimmer" style="height:320px;border-radius:16px" />

    <form v-else @submit.prevent="handleSubmit" novalidate>
      <div class="card" style="margin-bottom:16px">
        <p class="section-label">Property & category</p>
        <div class="form-grid" style="margin-top:12px">
          <div style="grid-column:1/-1">
            <label class="form-label" for="property_id">Property <span style="color:#dc2626">*</span></label>
            <select id="property_id" v-model="form.property_id" class="form-select" :class="fieldErrors.property_id ? 'input--err' : ''">
              <option value="">— Select your property —</option>
              <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }} ({{ p.address }})</option>
            </select>
            <p v-if="fieldErrors.property_id" class="form-error">{{ fieldErrors.property_id }}</p>
            <p style="font-size:0.72rem;color:var(--g400);margin-top:4px">You can only submit tickets for properties with an active lease.</p>
          </div>
          <div>
            <label class="form-label" for="category">Category <span style="color:#dc2626">*</span></label>
            <select id="category" v-model="form.category" class="form-select">
              <option value="maintenance">Maintenance (Facilities / Damage)</option>
              <option value="billing">Billing (Payment / Finance)</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="priority">Priority <span style="color:#dc2626">*</span></label>
            <select id="priority" v-model="form.priority" class="form-select">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:24px">
        <p class="section-label">Complaint details</p>
        <div style="display:flex;flex-direction:column;gap:16px;margin-top:12px">
          <div>
            <label class="form-label" for="title">Title <span style="color:#dc2626">*</span></label>
            <input id="title" v-model="form.title" type="text" class="form-input" :class="fieldErrors.title ? 'input--err' : ''" placeholder="e.g. Bedroom air conditioning leaking" />
            <p v-if="fieldErrors.title" class="form-error">{{ fieldErrors.title }}</p>
          </div>
          <div>
            <label class="form-label" for="description">Description <span style="color:#dc2626">*</span></label>
            <textarea id="description" v-model="form.description" class="form-textarea" :class="fieldErrors.description ? 'input--err' : ''" rows="5" placeholder="Describe the issue in detail — when it started, what you've already tried, and how urgent it is…" />
            <p v-if="fieldErrors.description" class="form-error">{{ fieldErrors.description }}</p>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-primary" :disabled="isSubmitting">
          {{ isSubmitting ? 'Submitting…' : 'Submit ticket' }}
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
</style>
