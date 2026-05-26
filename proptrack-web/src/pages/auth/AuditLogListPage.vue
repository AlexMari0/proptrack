<template>
  <div class="page-content">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Audit Trail Logs</h1>
        <p class="page-subtitle">Chronological tracking of system data changes and administrator activities</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-field select-field">
        <select 
          :value="filters.action" 
          @change="onFilterAction(($event.target as HTMLSelectElement).value)" 
          class="form-select-inline"
        >
          <option value="">All Actions</option>
          <option value="create">Create</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
        </select>
      </div>

      <div class="search-field select-field">
        <select 
          :value="filters.model" 
          @change="onFilterModel(($event.target as HTMLSelectElement).value)" 
          class="form-select-inline"
        >
          <option value="">All Resources</option>
          <option value="Property">Properties</option>
          <option value="Tenant">Tenants</option>
          <option value="Contract">Contracts</option>
          <option value="Invoice">Invoices</option>
          <option value="Ticket">Support Tickets</option>
        </select>
      </div>

      <button v-if="filters.action || filters.model" class="btn-ghost" @click="onReset">Reset</button>
    </div>

    <!-- Server Error -->
    <div v-if="error" class="alert alert--error mb-6">{{ error }}</div>

    <!-- Skeleton Loader -->
    <div v-if="isLoading" class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
      <div class="shimmer-table">
        <div v-for="i in 6" :key="i" class="shimmer-row" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="logs.length === 0" class="empty-state">
      <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M12 6V12h6"/>
        <circle cx="12" cy="12" r="10"/>
      </svg>
      <p class="empty-state__title">No activity logs found</p>
      <p class="empty-state__text">Activity history will appear here once actions are performed on tracked resources.</p>
    </div>

    <!-- Audit Logs Table -->
    <div v-else class="card" style="padding: 0; overflow: hidden; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.015);">
      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Operator</th>
              <th>Action</th>
              <th>Resource</th>
              <th>IP Address</th>
              <th style="text-align: right;">Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id" class="table-row">
              <td class="timestamp-col">
                {{ formatDateTime(log.created_at) }}
              </td>
              <td>
                <div class="operator-info">
                  <div class="operator-avatar">
                    {{ log.user ? log.user.name[0].toUpperCase() : 'S' }}
                  </div>
                  <div>
                    <p class="operator-name">{{ log.user ? log.user.name : 'System / Webhook' }}</p>
                    <p class="operator-role">{{ log.user ? log.user.roles.join(', ') : 'automation' }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge" :class="{
                  'badge--green': log.action === 'create',
                  'badge--amber': log.action === 'update',
                  'badge--red': log.action === 'delete'
                }">
                  {{ log.action }}
                </span>
              </td>
              <td>
                <div class="resource-info">
                  <span class="resource-type">{{ log.model_type }}</span>
                  <span class="resource-id">{{ log.model_id.substring(0, 8) }}…</span>
                </div>
              </td>
              <td class="ip-col">{{ log.ip_address || 'local' }}</td>
              <td style="text-align: right;">
                <button class="btn-ghost btn-sm" @click="viewDetails(log)">
                  Inspect
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="pagination" style="margin-top: 24px;">
      <button class="pagination__btn" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="pagination__info">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button class="pagination__btn" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>

    <!-- Details Modal Drawer -->
    <Transition name="fade">
      <div v-if="activeLog" class="modal-backdrop" @click="closeDetails" role="dialog" aria-modal="true">
        <Transition name="slide">
          <div class="modal-drawer" @click.stop>
            <div class="modal-drawer__header">
              <div>
                <h3 class="modal-drawer__title">Log Details Inspector</h3>
                <p class="modal-drawer__sub">Audit ID: {{ activeLog.id }}</p>
              </div>
              <button class="modal-drawer__close" @click="closeDetails" aria-label="Close details">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="modal-drawer__body">
              <!-- Meta Summary -->
              <div class="meta-summary-box">
                <div class="meta-item">
                  <span class="meta-label">Action Performed</span>
                  <span class="badge" :class="{
                    'badge--green': activeLog.action === 'create',
                    'badge--amber': activeLog.action === 'update',
                    'badge--red': activeLog.action === 'delete'
                  }">{{ activeLog.action }}</span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Resource Type</span>
                  <span class="meta-val font-semibold">{{ activeLog.model_type }}</span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Operator</span>
                  <span class="meta-val">{{ activeLog.user ? activeLog.user.name : 'System / Webhook' }}</span>
                </div>
                <div class="meta-item">
                  <span class="meta-label">Timestamp</span>
                  <span class="meta-val">{{ formatDateTime(activeLog.created_at) }}</span>
                </div>
              </div>

              <!-- Changes Inspector Table -->
              <h4 class="inspector-section-title">Database Changes Diff</h4>

              <!-- Case 1: UPDATE Diff -->
              <div v-if="activeLog.action === 'update'" class="diff-container">
                <div class="diff-header">
                  <span class="diff-h-field">Field</span>
                  <span class="diff-h-old">Old Value</span>
                  <span class="diff-h-new">New Value</span>
                </div>
                <div class="diff-body">
                  <div v-for="(newVal, key) in activeLog.new_values" :key="key" class="diff-row">
                    <span class="diff-field">{{ key }}</span>
                    <span class="diff-old-val">
                      {{ activeLog.old_values && activeLog.old_values[key] !== undefined ? activeLog.old_values[key] : '-' }}
                    </span>
                    <span class="diff-new-val">{{ newVal }}</span>
                  </div>
                </div>
              </div>

              <!-- Case 2: CREATE Initial values -->
              <div v-else-if="activeLog.action === 'create'" class="diff-container">
                <div class="diff-header diff-header--single">
                  <span class="diff-h-field">Field</span>
                  <span class="diff-h-new">Initial Values</span>
                </div>
                <div class="diff-body">
                  <div v-for="(val, key) in activeLog.new_values" :key="key" class="diff-row diff-row--single">
                    <span class="diff-field">{{ key }}</span>
                    <span class="diff-new-val">{{ val }}</span>
                  </div>
                </div>
              </div>

              <!-- Case 3: DELETE Captured values -->
              <div v-else-if="activeLog.action === 'delete'" class="diff-container">
                <div class="diff-header diff-header--single">
                  <span class="diff-h-field">Field</span>
                  <span class="diff-h-old">Deleted Attributes</span>
                </div>
                <div class="diff-body">
                  <div v-for="(val, key) in activeLog.old_values" :key="key" class="diff-row diff-row--single">
                    <span class="diff-field">{{ key }}</span>
                    <span class="diff-old-val">{{ val }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useAudit } from '@/composables/useAudit';
import type { AuditLog } from '@/types/audit';

export default defineComponent({
  name: 'AuditLogListPage',
  setup() {
    const { logs, meta, isLoading, error, filters, fetchLogs, applyFilters, resetFilters, changePage } = useAudit();
    const activeLog = ref<AuditLog | null>(null);

    function onFilterAction(action: string) {
      applyFilters({ action });
    }

    function onFilterModel(model: string) {
      applyFilters({ model });
    }

    function onReset() {
      resetFilters();
    }

    function formatDateTime(dateStr: string): string {
      return new Date(dateStr).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }

    function viewDetails(log: AuditLog) {
      activeLog.value = log;
      document.body.style.overflow = 'hidden';
    }

    function closeDetails() {
      activeLog.value = null;
      document.body.style.overflow = '';
    }

    onMounted(() => {
      fetchLogs();
    });

    return {
      logs,
      meta,
      isLoading,
      error,
      filters,
      activeLog,
      onFilterAction,
      onFilterModel,
      onReset,
      changePage,
      formatDateTime,
      viewDetails,
      closeDetails
    };
  }
});
</script>

<style scoped>
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.select-field {
  min-width: 140px;
}

.form-select-inline {
  width: 100%;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--g200);
  background: #ffffff;
  color: var(--g700);
  font-family: 'Outfit', sans-serif;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  box-sizing: border-box;
}

.form-select-inline:focus {
  outline: 2px solid var(--amber-ring);
  border-color: var(--amber);
}

.shimmer-table {
  padding: 16px;
}

.shimmer-row {
  height: 48px;
  background: linear-gradient(90deg, var(--g50) 25%, var(--g100) 50%, var(--g50) 75%);
  background-size: 200% 100%;
  animation: loadingShimmer 1.5s infinite;
  border-radius: 8px;
  margin-bottom: 12px;
}
.shimmer-row:last-child {
  margin-bottom: 0;
}

@keyframes loadingShimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.table-row {
  transition: background-color 0.15s ease;
}
.table-row:hover {
  background-color: var(--g50);
}

.timestamp-col {
  font-family: monospace;
  font-size: 0.75rem;
  color: var(--g600);
  font-weight: 500;
}

.operator-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.operator-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--g900);
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.operator-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--g900);
  margin: 0;
}

.operator-role {
  font-size: 0.6875rem;
  color: var(--g400);
  margin: 1px 0 0 0;
  text-transform: capitalize;
}

.resource-info {
  display: flex;
  flex-direction: column;
}

.resource-type {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--g900);
}

.resource-id {
  font-size: 0.6875rem;
  font-family: monospace;
  color: var(--g400);
  margin-top: 1px;
}

.ip-col {
  font-size: 0.78rem;
  font-family: monospace;
  color: var(--g500);
}

/* Modal backdrop & Drawer slide transition */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(26, 23, 18, 0.4);
  backdrop-filter: blur(4px);
  z-index: var(--z-modal);
  display: flex;
  justify-content: flex-end;
}

.modal-drawer {
  width: 100%;
  max-width: 580px;
  height: 100%;
  background: #ffffff;
  box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
}

.modal-drawer__header {
  padding: 24px 28px;
  border-bottom: 1px solid var(--g100);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-drawer__title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
}

.modal-drawer__sub {
  font-size: 0.72rem;
  font-family: monospace;
  color: var(--g400);
  margin: 4px 0 0 0;
}

.modal-drawer__close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--g100);
  background: transparent;
  color: var(--g500);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}
.modal-drawer__close:hover {
  background: var(--g50);
  color: var(--g900);
}
.modal-drawer__close svg {
  width: 14px;
  height: 14px;
}

.modal-drawer__body {
  padding: 28px;
  flex: 1;
  overflow-y: auto;
}

.meta-summary-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 24px;
  background: var(--g50);
  border-radius: 12px;
  border: 1px solid var(--g100);
  padding: 16px 20px;
  margin-bottom: 28px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--g400);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.meta-val {
  font-size: 0.8125rem;
  color: var(--g700);
}

.inspector-section-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0 0 12px 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Diff elements */
.diff-container {
  border: 1px solid var(--g100);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(26,23,18,0.01);
}

.diff-header {
  display: grid;
  grid-template-columns: 1fr 1.25fr 1.25fr;
  background: var(--g50);
  border-bottom: 1px solid var(--g100);
  padding: 10px 16px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--g500);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.diff-header--single {
  grid-template-columns: 1.2fr 2.8fr;
}

.diff-body {
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.diff-row {
  display: grid;
  grid-template-columns: 1fr 1.25fr 1.25fr;
  padding: 12px 16px;
  border-bottom: 1px solid var(--g100);
  align-items: center;
  gap: 12px;
  box-sizing: border-box;
}
.diff-row:last-child {
  border-bottom: none;
}

.diff-row--single {
  grid-template-columns: 1.2fr 2.8fr;
}

.diff-field {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--g900);
  font-family: monospace;
}

.diff-old-val {
  font-size: 0.75rem;
  font-family: monospace;
  color: #b91c1c;
  background: #fee2e2;
  padding: 4px 8px;
  border-radius: 6px;
  word-break: break-all;
  line-height: 1.4;
  text-decoration: line-through;
}

.diff-new-val {
  font-size: 0.75rem;
  font-family: monospace;
  color: #15803d;
  background: #d1fae5;
  padding: 4px 8px;
  border-radius: 6px;
  word-break: break-all;
  line-height: 1.4;
}

/* Transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.slide-enter-active, .slide-leave-active {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
}
</style>
