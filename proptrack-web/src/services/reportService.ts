import api from '@/plugins/axios';
import type { FinancialReport } from '@/types/report';

export interface ReportParams {
  year: number;
  month?: number;
  property_id?: string;
}

export interface ReportApiResponse {
  data: FinancialReport;
  message: string;
}

export const reportService = {
  /**
   * Fetch the overall financial report summary.
   */
  async getSummary(params: { year: number; month?: number }): Promise<ReportApiResponse> {
    const response = await api.get<ReportApiResponse>('/api/v1/reports/financial', {
      params,
    });
    return response.data;
  },

  /**
   * Fetch a single property's financial report summary.
   */
  async getPropertySummary(
    propertyId: string,
    params: { year: number; month?: number }
  ): Promise<ReportApiResponse> {
    const response = await api.get<ReportApiResponse>(
      `/api/v1/reports/financial/${propertyId}`,
      { params }
    );
    return response.data;
  },

  /**
   * Trigger binary PDF financial report download.
   */
  async downloadExport(params: ReportParams): Promise<void> {
    const response = await api.get('/api/v1/reports/financial/export', {
      params,
      responseType: 'blob',
    });

    const period = params.month
      ? `${params.year}-${String(params.month).padStart(2, '0')}`
      : `${params.year}`;
    const filename = `financial-report-${period}.pdf`;

    const url = window.URL.createObjectURL(
      new Blob([response.data], { type: 'application/pdf' })
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.URL.revokeObjectURL(url);
  },
};
