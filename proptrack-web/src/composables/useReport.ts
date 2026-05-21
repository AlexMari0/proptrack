import { ref } from 'vue';
import { reportService, type ReportParams } from '@/services/reportService';
import type { FinancialReport } from '@/types/report';

export function useReport() {
  const reportData = ref<FinancialReport | null>(null);
  const isLoading = ref(false);
  const isExporting = ref(false);
  const error = ref<string | null>(null);

  /**
   * Fetch overall platform financial summary.
   */
  async function fetchSummary(year: number, month?: number): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await reportService.getSummary({ year, month });
      reportData.value = response.data;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load overall financial summary.';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch specific property financial summary.
   */
  async function fetchPropertySummary(
    propertyId: string,
    year: number,
    month?: number
  ): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await reportService.getPropertySummary(propertyId, { year, month });
      reportData.value = response.data;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load property financial summary.';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Trigger PDF report generation & download.
   */
  async function exportReport(params: ReportParams): Promise<boolean> {
    isExporting.value = true;
    error.value = null;
    try {
      await reportService.downloadExport(params);
      return true;
    } catch (err: any) {
      error.value = 'Failed to generate and download PDF report.';
      return false;
    } finally {
      isExporting.value = false;
    }
  }

  return {
    reportData,
    isLoading,
    isExporting,
    error,
    fetchSummary,
    fetchPropertySummary,
    exportReport,
  };
}
