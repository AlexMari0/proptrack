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

onMounted(() => {
  fetchTickets()
})

// Debounce search input
watch(searchInput, (value) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters({ search: value })
  }, 400)
})

function onStatusFilter(status: TicketStatus | '') {
  applyFilters({ status })
}

function onCategoryFilter(category: TicketCategory | '') {
  applyFilters({ category })
}

function onPriorityFilter(priority: TicketPriority | '') {
  applyFilters({ priority })
}

function onReset() {
  searchInput.value = ''
  resetFilters()
  fetchTickets()
}

const statusTabs = [
  { label: 'All Tickets', value: '' },
  { label: 'Open', value: 'open' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Closed', value: 'closed' },
] as const

const categoryOptions = [
  { label: 'All Categories', value: '' },
  { label: 'Maintenance', value: 'maintenance' },
  { label: 'Billing', value: 'billing' },
  { label: 'Other', value: 'other' },
] as const

const priorityOptions = [
  { label: 'All Priorities', value: '' },
  { label: 'Low', value: 'low' },
  { label: 'Medium', value: 'medium' },
  { label: 'High', value: 'high' },
] as const

function getPriorityBadgeClass(priority: TicketPriority) {
  switch (priority) {
    case 'high': return 'priority-badge priority-badge--high'
    case 'medium': return 'priority-badge priority-badge--medium'
    case 'low': return 'priority-badge priority-badge--low'
    default: return 'priority-badge'
  }
}

function getStatusBadgeClass(status: TicketStatus) {
  switch (status) {
    case 'open': return 'status-tag status-tag--open'
    case 'in_progress': return 'status-tag status-tag--progress'
    case 'resolved': return 'status-tag status-tag--resolved'
    case 'closed': return 'status-tag status-tag--closed'
    default: return 'status-tag'
  }
}
</script>

<template>
  <div class="page">
    <!-- Header -->
    <div class="page__header">
      <div>
        <h1 class="page__title">Keluhan & Tiket</h1>
        <p class="page__subtitle">
          {{ meta ? `${meta.total} tiket keluhan terdaftar` : 'Pantau dan kelola laporan keluhan properti' }}
        </p>
      </div>
      <RouterLink v-if="isTenant" to="/tickets/create" class="btn btn--primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
        </svg>
        Buat Tiket Baru
      </RouterLink>
    </div>

    <!-- Filters Row -->
    <div class="filters">
      <!-- Search -->
      <div class="search-box">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
        <input
          v-model="searchInput"
          type="text"
          placeholder="Cari berdasarkan judul atau nomor tiket..."
          class="search-box__input"
        />
        <button v-if="searchInput" class="search-box__clear" @click="searchInput = ''; onReset()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
          </svg>
        </button>
      </div>

      <!-- Category Filter -->
      <select 
        class="filter-select" 
        :value="filters.category" 
        @change="onCategoryFilter(($event.target as HTMLSelectElement).value as TicketCategory | '')"
      >
        <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <!-- Priority Filter -->
      <select 
        class="filter-select" 
        :value="filters.priority" 
        @change="onPriorityFilter(($event.target as HTMLSelectElement).value as TicketPriority | '')"
      >
        <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <!-- Reset -->
      <button v-if="filters.search || filters.status || filters.category || filters.priority" class="btn btn--ghost btn--sm" @click="onReset">
        Reset
      </button>
    </div>

    <!-- Status Tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        :class="['status-tab', { 'status-tab--active': filters.status === tab.value }]"
        @click="onStatusFilter(tab.value as TicketStatus | '')"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="table-skeleton">
      <div v-for="i in 6" :key="i" class="table-skeleton__row" />
    </div>

    <!-- Empty State -->
    <div v-else-if="!isLoading && tickets.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path fill-rule="evenodd" d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z" />
        <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198c.03-.028.061-.056.091-.086L12 5.432z" />
      </svg>
      <h3>Tidak ada tiket ditemukan</h3>
      <p>{{ searchInput ? 'Coba gunakan kata kunci pencarian yang lain.' : 'Belum ada tiket keluhan yang diajukan saat ini.' }}</p>
      <RouterLink v-if="isTenant && !searchInput" to="/tickets/create" class="btn btn--primary">
        Buat Tiket Keluhan
      </RouterLink>
    </div>

    <!-- Tickets Table -->
    <div v-else class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Nomor Tiket</th>
            <th>Judul Keluhan</th>
            <th>Properti</th>
            <th>Kategori</th>
            <th>Prioritas</th>
            <th>Pelapor</th>
            <th>Ditugaskan Ke</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="ticket in tickets"
            :key="ticket.id"
            class="table__row"
            @click="router.push({ name: 'ticket-detail', params: { id: ticket.id } })"
          >
            <td class="text-mono text-primary font-bold">
              {{ ticket.ticket_number }}
            </td>
            <td>
              <div class="ticket-title-container">
                <span class="ticket-title-text">{{ ticket.title }}</span>
              </div>
            </td>
            <td class="text-muted">{{ ticket.property.name }}</td>
            <td>
              <span class="category-badge">{{ ticket.category }}</span>
            </td>
            <td>
              <span :class="getPriorityBadgeClass(ticket.priority)">
                {{ ticket.priority }}
              </span>
            </td>
            <td class="text-muted">{{ ticket.submitted_by.name }}</td>
            <td class="text-muted">
              {{ ticket.assigned_to ? ticket.assigned_to.name : 'Belum Ditugaskan' }}
            </td>
            <td>
              <span :class="getStatusBadgeClass(ticket.status)">
                {{ ticket.status }}
              </span>
            </td>
            <td class="text-muted text-sm">
              {{ new Date(ticket.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
            </td>
            <td>
              <button
                class="row-action"
                @click.stop="router.push({ name: 'ticket-detail', params: { id: ticket.id } })"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
        </svg>
      </button>
      <span class="pagination__info">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 32px; max-width: 1280px; margin: 0 auto; }
.page__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.page__title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); margin: 0 0 4px; }
.page__subtitle { font-size: 0.875rem; color: var(--color-text-muted); margin: 0; }

/* Filters */
.filters { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.search-box { position: relative; display: flex; align-items: center; flex: 1; min-width: 200px; }
.search-box > svg { position: absolute; left: 12px; width: 18px; height: 18px; color: var(--color-text-muted); pointer-events: none; }
.search-box__input { width: 100%; padding: 10px 36px 10px 38px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface); color: var(--color-text); font-size: 0.875rem; outline: none; transition: border-color 0.2s; }
.search-box__input:focus { border-color: var(--color-primary); }
.search-box__clear { position: absolute; right: 10px; background: none; border: none; color: var(--color-text-muted); cursor: pointer; display: flex; padding: 2px; }
.search-box__clear svg { width: 16px; height: 16px; }

.filter-select { padding: 10px 14px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-surface); color: var(--color-text); font-size: 0.875rem; outline: none; cursor: pointer; transition: border-color 0.2s; }
.filter-select:focus { border-color: var(--color-primary); }

/* Status tabs */
.status-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.status-tab { padding: 6px 16px; border-radius: 20px; border: 1px solid var(--color-border); background: transparent; color: var(--color-text-muted); font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.status-tab:hover { border-color: var(--color-primary); color: var(--color-primary); }
.status-tab--active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }

/* Table */
.table-wrapper { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; overflow-x: auto; margin-bottom: 24px; }
.table { width: 100%; border-collapse: collapse; min-width: 900px; }
.table thead tr { border-bottom: 1px solid var(--color-border); }
.table th { padding: 12px 16px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted); }
.table__row { cursor: pointer; transition: background 0.15s; }
.table__row:hover { background: var(--color-surface-alt); }
.table__row:not(:last-child) { border-bottom: 1px solid var(--color-border); }
.table td { padding: 14px 16px; font-size: 0.875rem; color: var(--color-text); vertical-align: middle; }
.text-muted { color: var(--color-text-muted) !important; }
.text-mono { font-family: monospace; font-size: 0.8rem !important; }
.text-primary { color: var(--color-primary) !important; }
.font-bold { font-weight: 700; }
.text-sm { font-size: 0.8rem !important; }

.ticket-title-container { display: flex; align-items: center; }
.ticket-title-text { font-weight: 600; color: var(--color-text); }

/* Badges & Tags */
.category-badge { font-size: 0.7rem; font-weight: 600; text-transform: capitalize; padding: 3px 8px; border-radius: 6px; background: rgba(99, 102, 241, 0.08); color: var(--color-primary); border: 1px solid rgba(99, 102, 241, 0.15); }

.priority-badge { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 20px; }
.priority-badge--high { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.priority-badge--medium { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.priority-badge--low { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }

.status-tag { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 20px; }
.status-tag--open { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-tag--progress { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.status-tag--resolved { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.status-tag--closed { background: rgba(100, 116, 139, 0.15); color: #64748b; }

/* Row action */
.row-action { background: none; border: none; color: var(--color-text-muted); cursor: pointer; padding: 4px; display: flex; border-radius: 6px; transition: color 0.2s, background 0.2s; }
.row-action:hover { color: var(--color-primary); background: rgba(99, 102, 241, 0.1); }
.row-action svg { width: 18px; height: 18px; }

/* Skeleton */
.table-skeleton { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; overflow: hidden; margin-bottom: 24px; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.table-skeleton__row { height: 48px; border-radius: 8px; background: linear-gradient(90deg, var(--color-surface-alt) 25%, var(--color-border) 50%, var(--color-surface-alt) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* Empty state */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 80px 24px; text-align: center; color: var(--color-text-muted); }
.empty-state svg { width: 56px; height: 56px; opacity: 0.3; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: var(--color-text); margin: 0; }
.empty-state p { font-size: 0.875rem; margin: 0; }

/* Pagination */
.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; }
.pagination__btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.pagination__btn:hover:not(:disabled) { border-color: var(--color-primary); color: var(--color-primary); }
.pagination__btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination__btn svg { width: 18px; height: 18px; }
.pagination__info { font-size: 0.875rem; color: var(--color-text-muted); }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
.btn svg { width: 18px; height: 18px; }
.btn--primary { background: var(--color-primary); color: #fff; }
.btn--primary:hover { background: var(--color-primary-hover); }
.btn--ghost { background: transparent; border: 1px solid var(--color-border); color: var(--color-text); }
.btn--ghost:hover { border-color: var(--color-primary); color: var(--color-primary); }
.btn--sm { padding: 7px 14px; font-size: 0.8rem; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 20px; }
.alert--error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
</style>
