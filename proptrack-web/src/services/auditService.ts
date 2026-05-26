import api from '@/plugins/axios';
import type { AuditLog } from '@/types/audit';

export interface AuditLogParams {
  page?: number;
  per_page?: number;
  action?: string;
  model?: string;
}

export interface AuditApiResponse {
  data: AuditLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message: string;
}

export const auditService = {
  /**
   * Fetch the paginated chronological audit logs list.
   */
  async list(params: AuditLogParams): Promise<AuditApiResponse> {
    const response = await api.get<AuditApiResponse>('/api/v1/audit-logs', {
      params,
    });
    return response.data;
  },
};
