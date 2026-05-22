<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTicket } from '@/composables/useTicket'
import { useAuthStore } from '@/stores/auth'
import type { TicketStatus, TicketCategory, TicketPriority } from '@/types/ticket'

const router = useRouter()
const { tickets, meta, filters, isLoading, error, fetchTickets, applyFilters, changePage, resetFilters } = useTicket()
const authStore = useAuthStore()

const searchInput = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const isTenant = computed(() =>
  authStore.user?.roles?.includes('tenant') ?? false
)

onMounted(() => fetchTickets())

watch(searchInput, (value) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters({ search: value }), 400)
})

function onStatusFilter(status: TicketStatus | '') { applyFilters({ status }) }
function onCategoryFilter(category: TicketCategory | '') { applyFilters({ category }) }
function onPriorityFilter(priority: TicketPriority | '') { applyFilters({ priority }) }
function onReset() { searchInput.value = ''; resetFilters(); fetchTickets() }

const statusTabs = [
  { label: 'All',         value: '' },
  { label: 'Open',        value: 'open' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Resolved',    value: 'resolved' },
  { label: 'Closed',      value: 'closed' },
] as const

const categoryOptions = [
  { label: 'All categories', value: '' },
  { label: 'Maintenance',    value: 'maintenance' },
  { label: 'Billing',        value: 'billing' },
  { label: 'Other',          value: 'other' },
] as const

const priorityOptions = [
  { label: 'All priorities', value: '' },
  { label: 'Low',            value: 'low' },
  { label: 'Medium',         value: 'medium' },
  { label: 'High',           value: 'high' },
] as const

function priorityBadge(p: TicketPriority): string {
  return p === 'high' ? 'badge badge--red' : p === 'medium' ? 'badge badge--amber' : 'badge badge--gray'
}

function statusBadge(s: TicketStatus): string {
  return s === 'resolved' ? 'badge badge--green'
    : s === 'in_progress' ? 'badge badge--indigo'
    : s === 'open' ? 'badge badge--amber'
    : 'badge badge--gray'
}
</script>

<template>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Support tickets</h1>
        <p class="page-subtitle">{{ meta ? `${meta.total} tickets registered` : 'Track and manage property complaints' }}</p>
      </div>
      <RouterLink v-if="isTenant" to="/tickets/create" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        New ticket
      </RouterLink>
    </div>

    <div class="filter-bar">
      <div class="search-field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input v-model="searchInput" type="search" placeholder="Search by title or ticket number…" aria-label="Search tickets" />
      </div>
      <select class="form-select" style="width:auto" :value="filters.category" @change="onCategoryFilter(($event.target as HTMLSelectElement).value as TicketCategory | '')">
        <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <select class="form-select" style="width:auto" :value="filters.priority" @change="onPriorityFilter(($event.target as HTMLSelectElement).value as TicketPriority | '')">
        <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <button v-if="filters.search || filters.status || filters.category || filters.priority" class="btn-ghost" @click="onReset">Reset</button>
    </div>

    <div class="tab-bar">
      <button v-for="tab in statusTabs" :key="tab.value"
        :class="['tab', { 'tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as TicketStatus | '')">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div v-if="isLoading" class="card" style="padding:0;overflow:hidden">
      <div v-for="i in 6" :key="i" class="shimmer" style="height:54px;border-radius:0;margin-bottom:1px" />
    </div>

    <div v-else-if="!isLoading && tickets.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
        <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
      </svg>
      <p class="empty-state__title">No tickets found</p>
      <p class="empty-state__text">{{ searchInput ? 'Try a different search term.' : 'No support tickets submitted yet.' }}</p>
      <RouterLink v-if="isTenant && !searchInput" to="/tickets/create" class="btn-primary" style="margin-top:4px">Submit a ticket</RouterLink>
    </div>

    <div v-else class="card" style="padding:0;overflow:hidden;overflow-x:auto">
      <table class="data-table" style="min-width:720px">
        <thead>
          <tr>
            <th>Ticket #</th>
            <th>Title</th>
            <th>Property</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ticket in tickets" :key="ticket.id" class="ticket-row" @click="router.push({ name: 'ticket-detail', params: { id: ticket.id } })">
            <td class="ticket-link">{{ ticket.ticket_number }}</td>
            <td style="font-weight:600;color:var(--g900)">{{ ticket.title }}</td>
            <td style="color:var(--g500)">{{ ticket.property.name }}</td>
            <td><span class="badge badge--indigo" style="text-transform:capitalize">{{ ticket.category }}</span></td>
            <td><span :class="priorityBadge(ticket.priority)" style="text-transform:capitalize">{{ ticket.priority }}</span></td>
            <td><span :class="statusBadge(ticket.status)" style="text-transform:capitalize;white-space:nowrap">{{ ticket.status.replace('_', ' ') }}</span></td>
            <td style="color:var(--g400);font-size:0.78rem;white-space:nowrap">{{ new Date(ticket.created_at).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' }) }}</td>
            <td>
              <button class="row-btn" @click.stop="router.push({ name: 'ticket-detail', params: { id: ticket.id } })" aria-label="View ticket">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="pagination__info">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.ticket-row { cursor: pointer; }
.ticket-link {
  font-family: monospace;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-primary);
  transition: color 0.15s;
}
.ticket-row:hover .ticket-link {
  text-decoration: underline;
  color: var(--color-primary-hover, #4f46e5);
}
.row-btn {
  width: 28px; height: 28px; border: none; background: none; cursor: pointer;
  color: var(--g400); display: flex; align-items: center; justify-content: center;
  border-radius: 6px; transition: color 0.15s, background 0.15s;
}
.row-btn:hover { color: var(--g900); background: var(--g100); }
.row-btn svg { width: 16px; height: 16px; }
</style>
