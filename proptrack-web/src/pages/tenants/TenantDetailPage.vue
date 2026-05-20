<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenant } from '@/composables/useTenant'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedTenant, isLoading, isSubmitting, error, fetchTenant, deleteTenant } = useTenant()

const tenantId = computed(() => route.params.id as string)

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const canDelete = computed(() =>
  authStore.user?.roles?.includes('admin') ?? false,
)

onMounted(() => fetchTenant(tenantId.value))

async function handleDelete() {
  if (!confirm(`Delete tenant "${selectedTenant.value?.name}"? This cannot be undone.`)) return
  await deleteTenant(tenantId.value)
}
</script>

<template>
  <div class="page">
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back to Tenants
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Skeleton -->
    <div v-if="isLoading" class="skeleton-wrap">
      <div class="skeleton-header" />
      <div class="skeleton-grid">
        <div v-for="i in 6" :key="i" class="skeleton-card" />
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="selectedTenant" class="detail">
      <!-- Header -->
      <div class="detail__header">
        <div class="detail__identity">
          <div class="avatar">{{ selectedTenant.name.charAt(0).toUpperCase() }}</div>
          <div>
            <h1 class="detail__name">{{ selectedTenant.name }}</h1>
            <p class="detail__email">{{ selectedTenant.email }}</p>
          </div>
        </div>

        <div v-if="canManage" class="detail__actions">
          <RouterLink
            :to="{ name: 'tenant-edit', params: { id: selectedTenant.id } }"
            class="btn btn--ghost"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
            </svg>
            Edit
          </RouterLink>
          <button v-if="canDelete" class="btn btn--danger" :disabled="isSubmitting" @click="handleDelete">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5z" clip-rule="evenodd" />
            </svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Info grid -->
      <div class="info-grid">
        <div class="info-card">
          <span class="info-card__label">Phone</span>
          <span class="info-card__value">{{ selectedTenant.phone }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">KTP Number</span>
          <span class="info-card__value info-card__value--mono">{{ selectedTenant.id_card_number }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Emergency Contact</span>
          <span class="info-card__value">{{ selectedTenant.emergency_contact_name }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Emergency Phone</span>
          <span class="info-card__value">{{ selectedTenant.emergency_contact_phone }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Registered</span>
          <span class="info-card__value">
            {{ new Date(selectedTenant.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
          </span>
        </div>
      </div>

      <!-- Active Contract -->
      <div class="contract-section">
        <h2 class="section-title">Active Contract</h2>

        <div v-if="selectedTenant.active_contract" class="contract-card">
          <div class="contract-card__row">
            <span class="contract-card__label">Property</span>
            <span class="contract-card__value">{{ selectedTenant.active_contract.property.name }}</span>
          </div>
          <div class="contract-card__row">
            <span class="contract-card__label">Period</span>
            <span class="contract-card__value">
              {{ selectedTenant.active_contract.start_date }} → {{ selectedTenant.active_contract.end_date }}
            </span>
          </div>
          <div class="contract-card__row">
            <span class="contract-card__label">Monthly Rent</span>
            <span class="contract-card__value contract-card__value--price">
              {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(selectedTenant.active_contract.monthly_rent) }}
            </span>
          </div>
          <span class="badge badge--active">Active</span>
        </div>

        <div v-else class="no-contract">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0016.5 9h-1.875a1.875 1.875 0 01-1.875-1.875V5.25A3.75 3.75 0 009 1.5H5.625zM7.5 15a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5A.75.75 0 017.5 15zm.75-6.75a.75.75 0 000 1.5H12a.75.75 0 000-1.5H8.25z" clip-rule="evenodd" />
          </svg>
          <p>No active contract</p>
          <span class="no-contract__hint">Contracts will be linked in Phase 2.3</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 900px; margin: 0 auto; }

.back-btn { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; color: var(--color-text-muted); font-size: 0.875rem; cursor: pointer; padding: 0; margin-bottom: 24px; transition: color 0.2s; }
.back-btn:hover { color: var(--color-primary); }
.back-btn svg { width: 18px; height: 18px; }

/* Header */
.detail__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 32px; flex-wrap: wrap; }
.detail__identity { display: flex; align-items: center; gap: 16px; }
.avatar { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), #818cf8); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.detail__name { font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.detail__email { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }
.detail__actions { display: flex; gap: 10px; }

/* Info grid */
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 32px; }
.info-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
.info-card__label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.info-card__value { font-size: 0.9rem; color: var(--color-text); font-weight: 500; }
.info-card__value--mono { font-family: monospace; font-size: 0.85rem; letter-spacing: 0.05em; }

/* Contract section */
.section-title { font-size: 1rem; font-weight: 600; color: var(--color-text); margin: 0 0 14px; }

.contract-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
.contract-card__row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.contract-card__label { font-size: 0.8rem; color: var(--color-text-muted); font-weight: 500; }
.contract-card__value { font-size: 0.9rem; color: var(--color-text); font-weight: 500; }
.contract-card__value--price { font-size: 1rem; font-weight: 700; color: var(--color-primary); }

.badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 12px; border-radius: 20px; align-self: flex-start; }
.badge--active { background: rgba(34,197,94,0.15); color: #22c55e; }

.no-contract { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px 24px; background: var(--color-surface); border: 1px dashed var(--color-border); border-radius: 14px; text-align: center; color: var(--color-text-muted); }
.no-contract svg { width: 40px; height: 40px; opacity: 0.3; }
.no-contract p { margin: 0; font-size: 0.9rem; }
.no-contract__hint { font-size: 0.75rem; opacity: 0.6; }

/* Skeletons */
.skeleton-wrap { display: flex; flex-direction: column; gap: 24px; }
.skeleton-header { height: 80px; border-radius: 14px; }
.skeleton-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.skeleton-card { height: 80px; border-radius: 12px; }
.skeleton-header, .skeleton-card { background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn svg { width: 16px; height: 16px; }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn--danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); }
.btn--danger:hover { background: rgba(239,68,68,0.2); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
</style>
