import { ref } from 'vue';
import { auditService, type AuditLogParams } from '@/services/auditService';
import type { AuditLog } from '@/types/audit';

export function useAudit() {
  const logs = ref<AuditLog[]>([]);
  const meta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  const filters = ref({
    action: '',
    model: '',
  });

  async function fetchLogs(page = 1): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
      const params: AuditLogParams = {
        page,
        per_page: 15,
        action: filters.value.action || undefined,
        model: filters.value.model || undefined,
      };
      const response = await auditService.list(params);
      logs.value = response.data;
      meta.value = response.meta;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load system audit trail.';
    } finally {
      isLoading.value = false;
    }
  }

  function applyFilters(newFilters: { action?: string; model?: string }): void {
    if (newFilters.action !== undefined) filters.value.action = newFilters.action;
    if (newFilters.model !== undefined) filters.value.model = newFilters.model;
    fetchLogs(1);
  }

  function resetFilters(): void {
    filters.value = { action: '', model: '' };
    fetchLogs(1);
  }

  function changePage(page: number): void {
    fetchLogs(page);
  }

  return {
    logs,
    meta,
    isLoading,
    error,
    filters,
    fetchLogs,
    applyFilters,
    resetFilters,
    changePage,
  };
}
