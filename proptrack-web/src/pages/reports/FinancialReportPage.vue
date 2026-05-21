<template>
  <div class="report-page">
    <!-- Header Area -->
    <div class="report-header">
      <div>
        <h1 class="page-title">Laporan Keuangan</h1>
        <p class="page-subtitle">Analisis performa pendapatan dan outstanding piutang properti Anda</p>
      </div>
      <button 
        @click="handleExport" 
        :disabled="isExporting || isLoading" 
        class="btn-export"
      >
        <span v-if="isExporting" class="spinner"></span>
        <svg v-else class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
        {{ isExporting ? 'Mengekspor PDF...' : 'Ekspor PDF' }}
      </button>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
      <div class="filter-group">
        <label for="filter-property" class="filter-label">Properti</label>
        <select id="filter-property" v-model="selectedPropertyId" class="filter-select">
          <option value="">Semua Properti (All Properties)</option>
          <option v-for="prop in properties" :key="prop.id" :value="prop.id">
            {{ prop.name }}
          </option>
        </select>
      </div>

      <div class="filter-group">
        <label for="filter-year" class="filter-label">Tahun</label>
        <select id="filter-year" v-model="selectedYear" class="filter-select">
          <option v-for="y in yearsList" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="filter-month" class="filter-label">Bulan</label>
        <select id="filter-month" v-model="selectedMonth" class="filter-select">
          <option value="">Semua Bulan (Full Year)</option>
          <option v-for="(mName, idx) in monthsList" :key="idx" :value="idx + 1">
            {{ mName }}
          </option>
        </select>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error || propertyError" class="error-alert">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <span>{{ error || propertyError }}</span>
    </div>

    <!-- Loading Skeleton / Content -->
    <div v-if="isLoading" class="skeleton-grid">
      <div class="skeleton-card" v-for="i in 4" :key="i"></div>
    </div>

    <div v-else-if="reportData" class="report-content">
      <!-- KPI Grid -->
      <div class="kpi-grid">
        <!-- Invoiced -->
        <div class="kpi-card invoiced">
          <div class="kpi-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Total Tagihan (Invoiced)</span>
            <span class="kpi-value">Rp {{ formatCurrency(reportData.total_invoiced) }}</span>
          </div>
        </div>

        <!-- Collected -->
        <div class="kpi-card collected">
          <div class="kpi-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Total Diterima (Collected)</span>
            <span class="kpi-value">Rp {{ formatCurrency(reportData.total_collected) }}</span>
          </div>
        </div>

        <!-- Outstanding -->
        <div class="kpi-card outstanding">
          <div class="kpi-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Tunggakan (Outstanding)</span>
            <span class="kpi-value">Rp {{ formatCurrency(reportData.total_outstanding) }}</span>
          </div>
        </div>

        <!-- Rate -->
        <div class="kpi-card rate">
          <div class="kpi-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Rasio Pembayaran</span>
            <span class="kpi-value">{{ reportData.collection_rate }}%</span>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="charts-section">
        <!-- Monthly Trend Chart -->
        <div class="chart-card">
          <h2 class="chart-title">Tren Pendapatan Bulanan ({{ selectedYear }})</h2>
          <div class="chart-wrapper">
            <Bar 
              v-if="trendChartData.datasets[0].data.some(v => v > 0)" 
              :data="trendChartData" 
              :options="chartOptions" 
            />
            <div v-else class="chart-empty">
              <svg class="icon-empty" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <span>Belum ada data trend untuk tahun ini</span>
            </div>
          </div>
        </div>

        <!-- Property breakdown breakdown list -->
        <div class="breakdown-card">
          <h2 class="chart-title">Performa Pembayaran Properti</h2>
          <div class="table-container">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Nama Properti</th>
                  <th class="text-right">Tagihan</th>
                  <th class="text-right">Diterima</th>
                  <th class="text-right">Tunggakan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in reportData.by_property" :key="item.property_id">
                  <td class="font-bold">{{ item.property_name }}</td>
                  <td class="text-right font-mono">Rp {{ formatCurrency(item.invoiced) }}</td>
                  <td class="text-right font-mono text-emerald">Rp {{ formatCurrency(item.collected) }}</td>
                  <td class="text-right font-mono text-rose">Rp {{ formatCurrency(item.outstanding) }}</td>
                </tr>
                <tr v-if="reportData.by_property.length === 0">
                  <td colspan="4" class="text-center text-gray">
                    Tidak ada transaksi pada periode ini
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch, onMounted } from 'vue'
import { useReport } from '@/composables/useReport'
import { useProperty } from '@/composables/useProperty'
import { invoiceService } from '@/services/invoiceService'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale
} from 'chart.js'

// Register Chart.js elements
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

export default defineComponent({
  name: 'FinancialReportPage',
  components: {
    Bar
  },
  setup() {
    const { reportData, isLoading, isExporting, error, fetchSummary, fetchPropertySummary, exportReport } = useReport()
    const { properties, fetchProperties, error: propertyError } = useProperty()

    const selectedPropertyId = ref('')
    const selectedYear = ref(new Date().getFullYear())
    const selectedMonth = ref<number | ''>('')

    const yearsList = [2024, 2025, 2026, 2027]
    const monthsList = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ]

    // Invoices list to compute historical trend
    const yearlyInvoices = ref<any[]>([])

    // Load data
    async function loadData() {
      if (selectedPropertyId.value) {
        await fetchPropertySummary(
          selectedPropertyId.value,
          selectedYear.value,
          selectedMonth.value === '' ? undefined : selectedMonth.value
        )
      } else {
        await fetchSummary(
          selectedYear.value,
          selectedMonth.value === '' ? undefined : selectedMonth.value
        )
      }

      await loadYearlyTrend()
    }

    // Load all invoices for selected year to build 12-month trend visual chart
    async function loadYearlyTrend() {
      try {
        const response = await invoiceService.list({
          page: 1,
          per_page: 100,
          property_id: selectedPropertyId.value || undefined
        })
        
        // Filter invoices belonging to the selected year
        yearlyInvoices.value = response.data.filter((inv: any) => {
          return inv.billing_month.startsWith(`${selectedYear.value}-`)
        })
      } catch {
        yearlyInvoices.value = []
      }
    }

    // Compute chart metrics
    const trendChartData = computed(() => {
      const labelMonths = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'
      ]

      const invoicedSeries = Array(12).fill(0)
      const collectedSeries = Array(12).fill(0)

      yearlyInvoices.value.forEach((inv) => {
        const parts = inv.billing_month.split('-')
        const monthIndex = parseInt(parts[1], 10) - 1
        if (monthIndex >= 0 && monthIndex < 12) {
          invoicedSeries[monthIndex] += inv.amount
          if (inv.status === 'paid') {
            collectedSeries[monthIndex] += inv.amount
          }
        }
      })

      return {
        labels: labelMonths,
        datasets: [
          {
            label: 'Tagihan (Invoiced)',
            backgroundColor: '#818cf8',
            hoverBackgroundColor: '#6366f1',
            borderRadius: 6,
            data: invoicedSeries
          },
          {
            label: 'Diterima (Collected)',
            backgroundColor: '#34d399',
            hoverBackgroundColor: '#059669',
            borderRadius: 6,
            data: collectedSeries
          }
        ]
      }
    })

    const chartOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top' as const,
          labels: {
            font: { family: 'Inter, sans-serif', size: 11 },
            color: '#4b5563'
          }
        },
        tooltip: {
          callbacks: {
            label: (context: any) => {
              let label = context.dataset.label || ''
              if (label) label += ': '
              if (context.raw !== null) {
                label += 'Rp ' + context.raw.toLocaleString('id-ID')
              }
              return label
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#6b7280' }
        },
        y: {
          ticks: {
            color: '#6b7280',
            callback: (val: any) => 'Rp ' + (val / 1000000) + 'jt'
          }
        }
      }
    }

    // Export handler
    async function handleExport() {
      await exportReport({
        year: selectedYear.value,
        month: selectedMonth.value === '' ? undefined : selectedMonth.value,
        property_id: selectedPropertyId.value || undefined
      })
    }

    function formatCurrency(val: number): string {
      return val.toLocaleString('id-ID')
    }

    // Watchers for reactivity
    watch([selectedPropertyId, selectedYear, selectedMonth], () => {
      loadData()
    })

    onMounted(() => {
      fetchProperties()
      loadData()
    })

    return {
      properties,
      propertyError,
      reportData,
      isLoading,
      isExporting,
      error,
      selectedPropertyId,
      selectedYear,
      selectedMonth,
      yearsList,
      monthsList,
      trendChartData,
      chartOptions,
      handleExport,
      formatCurrency
    }
  }
})
</script>

<style scoped>
.report-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
  color: var(--color-foreground);
}

.page-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin-top: 4px;
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: var(--color-primary);
  color: #ffffff;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
}

.btn-export:hover:not(:disabled) {
  background-color: var(--color-primary-hover);
  transform: translateY(-1px);
}

.btn-export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.icon {
  width: 18px;
  height: 18px;
}

/* Spinner */
.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Filter Card */
.filter-card {
  background-color: var(--color-card-bg, #ffffff);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 12px;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.filter-select {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  font-size: 14px;
  color: var(--color-foreground);
  background-color: var(--color-input-bg, #f9fafb);
  outline: none;
  transition: border-color 0.2s ease;
}

.filter-select:focus {
  border-color: var(--color-primary);
  background-color: #ffffff;
}

/* KPI Cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.kpi-card {
  background-color: var(--color-card-bg, #ffffff);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.kpi-icon svg {
  width: 24px;
  height: 24px;
}

.kpi-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kpi-title {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}

.kpi-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--color-foreground);
}

/* Theme accent colors */
.invoiced .kpi-icon { background-color: #e0e7ff; color: #4f46e5; }
.collected .kpi-icon { background-color: #d1fae5; color: #059669; }
.outstanding .kpi-icon { background-color: #fee2e2; color: #dc2626; }
.rate .kpi-icon { background-color: #fef3c7; color: #d97706; }

/* Error state */
.error-alert {
  background-color: #fee2e2;
  border: 1px solid #fca5a5;
  color: #b91c1c;
  padding: 14px 20px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
}

/* Loading Skeleton */
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.skeleton-card {
  height: 90px;
  background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
  border-radius: 12px;
  border: 1px solid var(--color-border);
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Charts & Breakdown Layout */
.charts-section {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 20px;
}

@media (max-width: 1024px) {
  .charts-section {
    grid-template-columns: 1fr;
  }
}

.chart-card, .breakdown-card {
  background-color: var(--color-card-bg, #ffffff);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.chart-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-foreground);
  margin-bottom: 16px;
}

.chart-wrapper {
  height: 320px;
  position: relative;
}

.chart-empty {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #9ca3af;
  font-size: 14px;
}

.icon-empty {
  width: 48px;
  height: 48px;
}

/* Table Design */
.table-container {
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
}

.report-table th {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  padding: 12px 16px;
  border-bottom: 2px solid var(--color-border);
  background-color: var(--color-input-bg, #f9fafb);
  text-align: left;
}

.report-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border);
  font-size: 13px;
  color: var(--color-foreground);
}

.report-table tr:hover td {
  background-color: #f9fafb;
}

.font-bold { font-weight: 700; }
.font-mono { font-family: monospace; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-emerald { color: #059669; }
.text-rose { color: #dc2626; }
.text-gray { color: #9ca3af; }
</style>
