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
  property_id: '',
  category: 'maintenance',
  priority: 'medium',
  title: '',
  description: '',
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

onMounted(async () => {
  await loadProperties()
})

function validate(): boolean {
  // Reset previous errors
  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key as keyof CreateTicketPayload]
  })
  
  let isValid = true

  if (!form.property_id) {
    fieldErrors.property_id = 'Pilihlah properti keluhan.'
    isValid = false
  }
  if (!form.title.trim()) {
    fieldErrors.title = 'Judul keluhan wajib diisi.'
    isValid = false
  } else if (form.title.length > 255) {
    fieldErrors.title = 'Judul tidak boleh melebihi 255 karakter.'
    isValid = false
  }
  if (!form.description.trim()) {
    fieldErrors.description = 'Deskripsi keluhan wajib diisi.'
    isValid = false
  }

  return isValid
}

async function handleSubmit() {
  if (!validate()) return

  const success = await createTicket({
    property_id: form.property_id,
    category: form.category,
    priority: form.priority,
    title: form.title.trim(),
    description: form.description.trim(),
  })

  if (success) {
    // Navigated inside composable
  }
}
</script>

<template>
  <div class="page">
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Kembali
    </button>

    <div class="form-card">
      <h1 class="form-card__title">Buat Tiket Keluhan Baru</h1>
      <p class="form-card__subtitle">
        Sampaikan keluhan atau masalah terkait properti Anda secara detail agar tim pengelola kami dapat segera menanganinya.
      </p>

      <div v-if="propertiesLoading" class="form-loading">
        <span class="spinner" /> Memuat daftar properti...
      </div>

      <div v-if="error" class="alert alert--error">{{ error }}</div>

      <form v-if="!propertiesLoading" @submit.prevent="handleSubmit" novalidate>
        <p class="section-label">Detail Properti & Kategori</p>

        <!-- Property Dropdown -->
        <div class="field">
          <label class="field__label" for="property_id">Pilih Properti <span class="req">*</span></label>
          <select 
            id="property_id" 
            v-model="form.property_id" 
            class="field__input" 
            :class="{ 'field__input--error': fieldErrors.property_id }"
          >
            <option value="">— Pilih Properti Anda —</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">
              {{ p.name }} ({{ p.address }})
            </option>
          </select>
          <span v-if="fieldErrors.property_id" class="field__error">{{ fieldErrors.property_id }}</span>
          <span class="field__hint">Catatan: Anda hanya dapat mengirim tiket keluhan untuk properti yang memiliki kontrak sewa aktif dengan Anda.</span>
        </div>

        <div class="field-row">
          <!-- Category Select -->
          <div class="field">
            <label class="field__label" for="category">Kategori Masalah <span class="req">*</span></label>
            <select id="category" v-model="form.category" class="field__input">
              <option value="maintenance">Maintenance (Fasilitas/Kerusakan)</option>
              <option value="billing">Billing (Pembayaran/Keuangan)</option>
              <option value="other">Lainnya</option>
            </select>
          </div>

          <!-- Priority Select -->
          <div class="field">
            <label class="field__label" for="priority">Tingkat Prioritas <span class="req">*</span></label>
            <select id="priority" v-model="form.priority" class="field__input">
              <option value="low">Rendah (Low)</option>
              <option value="medium">Sedang (Medium)</option>
              <option value="high">Tinggi (High)</option>
            </select>
          </div>
        </div>

        <p class="section-label">Detail Keluhan</p>

        <!-- Complaint Title -->
        <div class="field">
          <label class="field__label" for="title">Judul Keluhan <span class="req">*</span></label>
          <input 
            id="title" 
            v-model="form.title" 
            type="text" 
            placeholder="Contoh: AC Kamar Tidur Utama Bocor/Berisik" 
            class="field__input" 
            :class="{ 'field__input--error': fieldErrors.title }"
          />
          <span v-if="fieldErrors.title" class="field__error">{{ fieldErrors.title }}</span>
        </div>

        <!-- Complaint Description -->
        <div class="field">
          <label class="field__label" for="description">Deskripsi Lengkap <span class="req">*</span></label>
          <textarea 
            id="description" 
            v-model="form.description" 
            rows="5"
            placeholder="Jelaskan secara detail mengenai masalah yang dialami (kapan mulai terjadi, gejala kerusakan, dll)..." 
            class="field__input field__textarea" 
            :class="{ 'field__input--error': fieldErrors.description }"
          ></textarea>
          <span v-if="fieldErrors.description" class="field__error">{{ fieldErrors.description }}</span>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn--ghost" @click="router.back()">Batal</button>
          <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
            <span v-if="isSubmitting" class="spinner" />
            Kirim Tiket Keluhan
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
.form-card__title { font-size: 1.4rem; font-weight: 700; color: var(--color-text); margin: 0 0 8px; }
.form-card__subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0 0 24px; line-height: 1.5; }
.section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--color-primary); margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--color-border); }
.section-label:first-of-type { margin-top: 0; }
.form-loading { display: flex; align-items: center; gap: 12px; color: var(--color-text-muted); padding: 32px 0; }

.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 18px; }
.field__label { font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px; }
.field__hint { font-size: 0.76rem; font-weight: 400; color: var(--color-text-muted); opacity: 0.8; margin-top: 4px; line-height: 1.4; }
.req { color: #ef4444; }

.field__input { padding: 10px 14px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface-alt); color: var(--color-text); font-size: 0.9rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.field__input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.field__input--error { border-color: #ef4444; }
.field__textarea { resize: vertical; min-height: 120px; }
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
