<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContract } from '@/composables/useContract'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { selectedContract, isLoading, isSubmitting, error, fetchContract, terminateContract, downloadDocument } = useContract()

const canManage = computed(() =>
  authStore.user?.roles?.some((r) => ['owner', 'admin'].includes(r)) ?? false,
)

const statusConfig = computed(() => {
  if (!selectedContract.value) return null
  return {
    active:     { label: 'Active',     cls: 'badge--active' },
    expired:    { label: 'Expired',    cls: 'badge--expired' },
    terminated: { label: 'Terminated', cls: 'badge--terminated' },
  }[selectedContract.value.status]
})

const formattedRent = computed(() => {
  if (!selectedContract.value) return ''
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(selectedContract.value.monthly_rent)
})

const formattedDeposit = computed(() => {
  if (!selectedContract.value) return ''
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(selectedContract.value.deposit_amount)
})

onMounted(() => fetchContract(route.params.id as string))

async function handleTerminate() {
  if (!confirm('Terminate this contract? This cannot be undone.')) return
  const ok = await terminateContract(route.params.id as string)
  if (ok) await fetchContract(route.params.id as string)
}

async function handleDownload() {
  await downloadDocument(route.params.id as string)
}
</script>

<template>
  <div class="page">
    <button class="back-btn" @click="router.back()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
      </svg>
      Back to Contracts
    </button>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Skeleton -->
    <div v-if="isLoading" class="skeleton-wrap">
      <div class="skeleton-header" />
      <div class="skeleton-grid">
        <div v-for="i in 6" :key="i" class="skeleton-card" />
      </div>
    </div>

    <div v-else-if="selectedContract" class="detail">
      <!-- Header -->
      <div class="detail__header">
        <div>
          <div class="detail__badges">
            <span v-if="statusConfig" :class="['badge', statusConfig.cls]">{{ statusConfig.label }}</span>
          </div>
          <h1 class="detail__title">
            {{ selectedContract.tenant.name }}
            <span class="detail__arrow">→</span>
            {{ selectedContract.property.name }}
          </h1>
          <p class="detail__sub">
            Contract ID: <span class="mono">{{ selectedContract.id }}</span>
          </p>
        </div>

        <div v-if="canManage" class="detail__actions">
          <button class="btn btn--ghost" :disabled="isSubmitting" @click="handleDownload">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L7.29 9.22a.75.75 0 00-1.08 1.04l3.25 3.5a.75.75 0 001.08 0l3.25-3.5a.75.75 0 10-1.08-1.04l-1.96 2.144V2.75z" />
              <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
            </svg>
            Download PDF
          </button>
          <RouterLink
            v-if="selectedContract.status === 'active'"
            :to="{ name: 'contract-edit', params: { id: selectedContract.id } }"
            class="btn btn--ghost"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
            </svg>
            Edit
          </RouterLink>
          <button
            v-if="selectedContract.status === 'active'"
            class="btn btn--danger"
            :disabled="isSubmitting"
            @click="handleTerminate"
          >
            Terminate
          </button>
        </div>
      </div>

      <!-- Info grid -->
      <div class="info-grid">
        <div class="info-card">
          <span class="info-card__label">Tenant</span>
          <RouterLink class="info-card__link" :to="{ name: 'tenant-detail', params: { id: selectedContract.tenant.id } }">
            {{ selectedContract.tenant.name }}
          </RouterLink>
        </div>
        <div class="info-card">
          <span class="info-card__label">Property</span>
          <RouterLink class="info-card__link" :to="{ name: 'property-detail', params: { id: selectedContract.property.id } }">
            {{ selectedContract.property.name }}
          </RouterLink>
        </div>
        <div class="info-card">
          <span class="info-card__label">Monthly Rent</span>
          <span class="info-card__value info-card__value--price">{{ formattedRent }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Deposit</span>
          <span class="info-card__value">{{ formattedDeposit }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Contract Period</span>
          <span class="info-card__value">{{ selectedContract.start_date }} → {{ selectedContract.end_date }}</span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Billing Date</span>
          <span class="info-card__value">Every day {{ selectedContract.billing_date }}</span>
        </div>
        <div v-if="selectedContract.terminated_at" class="info-card info-card--danger">
          <span class="info-card__label">Terminated On</span>
          <span class="info-card__value">
            {{ new Date(selectedContract.terminated_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
          </span>
        </div>
        <div class="info-card">
          <span class="info-card__label">Created</span>
          <span class="info-card__value">
            {{ new Date(selectedContract.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
          </span>
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

.detail__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.detail__badges { display: flex; gap: 8px; margin-bottom: 10px; }
.detail__title { font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin: 0 0 6px; }
.detail__arrow { color: var(--color-text-muted); font-weight: 400; margin: 0 4px; }
.detail__sub { font-size: 0.78rem; color: var(--color-text-muted); margin: 0; }
.mono { font-family: monospace; font-size: 0.75rem; }
.detail__actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* Badges */
.badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 12px; border-radius: 20px; }
.badge--active     { background: rgba(34,197,94,0.15); color: #22c55e; }
.badge--expired    { background: var(--color-surface-alt); color: var(--color-text-muted); border: 1px solid var(--color-border); }
.badge--terminated { background: rgba(239,68,68,0.12); color: #ef4444; }

/* Info grid */
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.info-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
.info-card--danger { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05); }
.info-card__label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.info-card__value { font-size: 0.9rem; color: var(--color-text); font-weight: 500; }
.info-card__value--price { font-size: 1.2rem; font-weight: 700; color: var(--color-primary); }
.info-card__link { font-size: 0.9rem; font-weight: 600; color: var(--color-primary); text-decoration: none; }
.info-card__link:hover { text-decoration: underline; }

/* Skeleton */
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
