<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Contract, ContractStatus } from '@/types/contract'

const props = defineProps<{
  contract: Contract
  showActions?: boolean
}>()

const emit = defineEmits<{
  terminate: [id: string]
  download: [id: string]
}>()

const router = useRouter()

const statusConfig = computed<Record<ContractStatus, { label: string; cls: string }>>(() => ({
  active:     { label: 'Active',      cls: 'badge--active' },
  expired:    { label: 'Expired',     cls: 'badge--expired' },
  terminated: { label: 'Terminated',  cls: 'badge--terminated' },
}))

const currentStatus = computed(() => statusConfig.value[props.contract.status])

const formattedRent = computed(() =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
  }).format(props.contract.monthly_rent),
)

const duration = computed(() => {
  const start = new Date(props.contract.start_date)
  const end = new Date(props.contract.end_date)
  const months =
    (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
  return `${months} months`
})

function goToDetail() {
  router.push({ name: 'contract-detail', params: { id: props.contract.id } })
}

function handleTerminateClick() {
  if (confirm('Are you sure you want to terminate this contract? This action is destructive and cannot be easily undone.')) {
    emit('terminate', props.contract.id)
  }
}
</script>

<template>
  <article class="contract-card" @click="goToDetail">
    <!-- Header row -->
    <div class="contract-card__header">
      <div class="contract-card__parties">
        <span class="contract-card__tenant">{{ contract.tenant.name }}</span>
        <svg class="contract-card__arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
        </svg>
        <span class="contract-card__property">{{ contract.property.name }}</span>
      </div>
      <span :class="['badge', currentStatus.cls]">{{ currentStatus.label }}</span>
    </div>

    <!-- Details -->
    <div class="contract-card__details">
      <div class="contract-card__detail">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
        </svg>
        <span>{{ contract.start_date }} → {{ contract.end_date }}</span>
        <span class="contract-card__duration">({{ duration }})</span>
      </div>
      <div class="contract-card__detail">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 10.818v2.614A3.13 3.13 0 0011.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 00-1.138-.432zM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 00-.35.13c-.14.065-.27.143-.386.233-.377.292-.514.627-.514.909 0 .184.058.39.33.615z" />
          <path fill-rule="evenodd" d="M9.99 2a8 8 0 100 16 8 8 0 000-16zM6.751 10.125c0-.892.448-1.58 1.05-2.033.307-.23.661-.406 1.043-.516V5.75a.75.75 0 011.5 0v1.803c.346.07.682.186.993.35.643.34 1.226.95 1.226 1.847v.15a.75.75 0 01-1.5 0v-.15c0-.268-.12-.494-.447-.684a2.82 2.82 0 00-.272-.134v2.21c.438.12.843.296 1.186.533.673.456 1.033 1.118 1.033 1.874 0 .756-.36 1.418-1.033 1.875-.343.237-.748.412-1.186.533V17a.75.75 0 01-1.5 0v-1.843a4.496 4.496 0 01-1.185-.535C6.87 14.172 6.5 13.48 6.5 12.625v-.15a.75.75 0 011.5 0v.15c0 .29.147.561.487.79.198.135.436.245.695.322v-2.364a4.496 4.496 0 01-.953-.361C7.198 11.705 6.75 11.017 6.75 10.125z" clip-rule="evenodd" />
        </svg>
        <span class="contract-card__rent">{{ formattedRent }}</span>
        <span class="contract-card__billing">· billing on day {{ contract.billing_date }}</span>
      </div>
    </div>

    <!-- Actions -->
    <div v-if="showActions && contract.status === 'active'" class="contract-card__actions" @click.stop>
      <button
        class="contract-card__action contract-card__action--download"
        @click.stop="emit('download', contract.id)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L7.29 9.22a.75.75 0 00-1.08 1.04l3.25 3.5a.75.75 0 001.08 0l3.25-3.5a.75.75 0 10-1.08-1.04l-1.96 2.144V2.75z" />
          <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
        </svg>
        PDF
      </button>
      <button
        class="contract-card__action contract-card__action--terminate"
        @click.stop="handleTerminateClick"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
        </svg>
        Terminate
      </button>
    </div>
  </article>
</template>

<style scoped>
.contract-card {
  background: #ffffff;
  border: 1px solid var(--g100);
  border-radius: 14px;
  padding: 18px 20px;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(26, 23, 18, 0.03);
  transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}

.contract-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(26, 23, 18, 0.08);
  border-color: var(--amber);
}

/* Header */
.contract-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.contract-card__parties {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.contract-card__tenant {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--g900);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.contract-card__arrow {
  width: 14px;
  height: 14px;
  color: var(--g400);
  flex-shrink: 0;
}

.contract-card__property {
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--g500);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Status badges */
.badge {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 10px;
  border-radius: 20px;
  flex-shrink: 0;
}
.badge--active     { background: rgba(34, 197, 94, 0.1); color: var(--status-green); }
.badge--expired    { background: var(--g50); color: var(--g500); border: 1px solid var(--g200); }
.badge--terminated { background: rgba(239, 68, 68, 0.08); color: var(--status-red); }

/* Details */
.contract-card__details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.contract-card__detail {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: var(--g500);
}

.contract-card__detail svg { width: 14px; height: 14px; flex-shrink: 0; }
.contract-card__rent { font-weight: 700; color: var(--amber); font-size: 0.875rem; }
.contract-card__billing { font-size: 0.75rem; }
.contract-card__duration { font-size: 0.75rem; opacity: 0.7; }

/* Actions */
.contract-card__actions {
  display: flex;
  gap: 8px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--g100);
}

.contract-card__action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--g200);
  transition: all 0.2s;
}

.contract-card__action svg { width: 14px; height: 14px; }

.contract-card__action--download {
  background: transparent;
  color: var(--g600);
}
.contract-card__action--download:hover {
  border-color: var(--g400);
  color: var(--g900);
  background: var(--g50);
}

.contract-card__action--terminate {
  background: transparent;
  color: var(--status-red);
  border: 1px solid rgba(239, 68, 68, 0.2);
}
.contract-card__action--terminate:hover {
  border-color: var(--status-red);
  color: var(--status-red);
  background: rgba(239, 68, 68, 0.08);
}
</style>
