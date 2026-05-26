<template>
  <div class="page-content">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Financial report</h1>
        <p class="page-subtitle">Revenue performance and outstanding receivables analysis</p>
      </div>
      <div style="display:flex; gap:10px">
        <button @click="handleExportCSV" :disabled="isLoading || !reportData" class="btn-ghost" style="border: 1px solid var(--g200); background: #ffffff; color: var(--g700);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="width:16px;height:16px;margin-right:6px"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
          Export Excel
        </button>
        <button @click="handleExport" :disabled="isExporting || isLoading" class="btn-primary">
          <svg v-if="!isExporting" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          {{ isExporting ? 'Exporting PDF…' : 'Export PDF' }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card filter-row">
      <div>
        <label class="form-label" for="filter-property">Property</label>
        <select id="filter-property" v-model="selectedPropertyId" class="form-select">
          <option value="">All properties</option>
          <option v-for="prop in properties" :key="prop.id" :value="prop.id">{{ prop.name }}</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="filter-year">Year</label>
        <select id="filter-year" v-model="selectedYear" class="form-select">
          <option v-for="y in yearsList" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="filter-month">Month</label>
        <select id="filter-month" v-model="selectedMonth" class="form-select">
          <option value="">Full year</option>
          <option v-for="(mName, idx) in monthsList" :key="idx" :value="idx + 1">{{ mName }}</option>
        </select>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error || propertyError" class="alert alert--error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;flex-shrink:0"><path d="M12 9v4"/><circle cx="12" cy="17" r=".5"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
      <span>{{ error || propertyError }}</span>
    </div>

    <!-- Skeleton -->
    <div v-if="isLoading" class="kpi-grid">
      <div v-for="i in 4" :key="i" class="shimmer" style="height:90px;border-radius:14px" />
    </div>

    <div v-else-if="reportData">
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card kpi-card--invoiced">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Total invoiced</span>
            <span class="kpi-value tabular-nums">Rp {{ formatCurrency(reportData.total_invoiced) }}</span>
          </div>
        </div>

        <div class="kpi-card kpi-card--collected">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Total collected</span>
            <span class="kpi-value tabular-nums">Rp {{ formatCurrency(reportData.total_collected) }}</span>
          </div>
        </div>

        <div :class="['kpi-card', 'kpi-card--outstanding', { 'kpi-card--critical': reportData.total_outstanding > 0 }]">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Outstanding</span>
            <span class="kpi-value tabular-nums">Rp {{ formatCurrency(reportData.total_outstanding) }}</span>
          </div>
        </div>

        <div class="kpi-card kpi-card--rate">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-title">Collection rate</span>
            <span class="kpi-value tabular-nums">{{ reportData.collection_rate }}%</span>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="charts-section">
        <div class="card">
          <p class="section-label">Monthly revenue trend ({{ selectedYear }})</p>
          <div class="chart-wrap">
            <Bar v-if="trendChartData.datasets[0].data.some((v: number) => v > 0)" :data="trendChartData" :options="chartOptions" />
            <div v-else class="chart-empty">
              <!-- Graph Skeleton background grid lines -->
              <div class="skeleton-grid" aria-hidden="true">
                <div v-for="i in 4" :key="i" class="skeleton-grid-line" />
              </div>
              
              <!-- Graph Skeleton bars -->
              <div class="skeleton-bars" aria-hidden="true">
                <div v-for="(h, idx) in [40, 25, 60, 45, 80, 55, 70, 40, 90, 65, 50, 30]" :key="idx" class="skeleton-bar-group">
                  <div class="skeleton-bar skeleton-bar--invoiced" :style="{ height: h + '%' }" />
                  <div class="skeleton-bar skeleton-bar--collected" :style="{ height: (h * 0.7) + '%' }" />
                </div>
              </div>

              <!-- Content Overlay -->
              <div class="chart-empty-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="empty-icon" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                <span class="empty-text">No trend data for this year</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="overflow:hidden">
          <p class="section-label">Property performance</p>
          <div style="overflow-x:auto;margin-top:12px">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Property</th>
                  <th style="text-align:right">Invoiced</th>
                  <th style="text-align:right">Collected</th>
                  <th style="text-align:right">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in reportData.by_property" :key="item.property_id">
                  <td style="font-weight:600;color:var(--g900)">{{ item.property_name }}</td>
                  <td style="text-align:right;font-family:monospace;font-size:0.78rem;color:var(--g600)">Rp {{ formatCurrency(item.invoiced) }}</td>
                  <td style="text-align:right;font-family:monospace;font-size:0.78rem;color:#16a34a;font-weight:600">Rp {{ formatCurrency(item.collected) }}</td>
                  <td style="text-align:right;font-family:monospace;font-size:0.78rem;color:#dc2626;font-weight:600">Rp {{ formatCurrency(item.outstanding) }}</td>
                </tr>
                <tr v-if="reportData.by_property.length === 0">
                  <td colspan="4" style="text-align:center;color:var(--g400);padding:32px">No transactions in this period</td>
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

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

export default defineComponent({
  name: 'FinancialReportPage',
  components: { Bar },
  setup() {
    const { reportData, isLoading, isExporting, error, fetchSummary, fetchPropertySummary, exportReport } = useReport()
    const { properties, fetchProperties, error: propertyError } = useProperty()

    const selectedPropertyId = ref('')
    const selectedYear = ref(new Date().getFullYear())
    const selectedMonth = ref<number | ''>('')

    const yearsList = [2024, 2025, 2026, 2027]
    const monthsList = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

    const yearlyInvoices = ref<any[]>([])

    async function loadData() {
      if (selectedPropertyId.value) {
        await fetchPropertySummary(selectedPropertyId.value, selectedYear.value, selectedMonth.value === '' ? undefined : selectedMonth.value)
      } else {
        await fetchSummary(selectedYear.value, selectedMonth.value === '' ? undefined : selectedMonth.value)
      }
      await loadYearlyTrend()
    }

    async function loadYearlyTrend() {
      try {
        const response = await invoiceService.list({ page: 1, per_page: 100, property_id: selectedPropertyId.value || undefined })
        yearlyInvoices.value = response.data.filter((inv: any) => inv.billing_month.startsWith(`${selectedYear.value}-`))
      } catch {
        yearlyInvoices.value = []
      }
    }

    const trendChartData = computed(() => {
      const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
      const invoicedSeries = Array(12).fill(0)
      const collectedSeries = Array(12).fill(0)
      yearlyInvoices.value.forEach((inv) => {
        const monthIndex = parseInt(inv.billing_month.split('-')[1], 10) - 1
        if (monthIndex >= 0 && monthIndex < 12) {
          invoicedSeries[monthIndex] += inv.amount
          if (inv.status === 'paid') collectedSeries[monthIndex] += inv.amount
        }
      })
      return {
        labels,
        datasets: [
          { label: 'Invoiced', backgroundColor: '#d4c09a', hoverBackgroundColor: '#c4a870', borderRadius: 6, data: invoicedSeries },
          { label: 'Collected', backgroundColor: '#86efac', hoverBackgroundColor: '#4ade80', borderRadius: 6, data: collectedSeries },
        ]
      }
    })

    const chartOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top' as const,
          labels: { font: { family: 'Outfit, sans-serif', size: 11 }, color: '#6b7280' }
        },
        tooltip: {
          callbacks: {
            label: (context: any) => {
              let label = context.dataset.label || ''
              if (label) label += ': '
              if (context.raw !== null) label += 'Rp ' + context.raw.toLocaleString('id-ID')
              return label
            }
          }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { family: 'Outfit, sans-serif' } } },
        y: { ticks: { color: '#9ca3af', font: { family: 'Outfit, sans-serif' }, callback: (val: any) => 'Rp ' + (val / 1000000) + 'jt' } }
      }
    }

    async function handleExport() {
      await exportReport({ year: selectedYear.value, month: selectedMonth.value === '' ? undefined : selectedMonth.value, property_id: selectedPropertyId.value || undefined })
    }

    function handleExportCSV() {
      if (!reportData.value) return

      const monthName = selectedMonth.value !== '' ? monthsList[selectedMonth.value - 1] : 'Full Year'
      const propName = selectedPropertyId.value 
        ? properties.value.find(p => p.id === selectedPropertyId.value)?.name || 'Unknown'
        : 'All Properties'

      // CSV Rows construction
      const rows = [
        ['PropTrack - Financial Report', ''],
        ['Period', `${monthName} ${selectedYear.value}`],
        ['Property', propName],
        ['Generated At', new Date().toLocaleString('id-ID')],
        ['', ''],
        ['Overall Performance Metrics', ''],
        ['Metric', 'Value'],
        ['Total Invoiced', `Rp ${formatCurrency(reportData.value.total_invoiced)}`],
        ['Total Collected', `Rp ${formatCurrency(reportData.value.total_collected)}`],
        ['Total Outstanding', `Rp ${formatCurrency(reportData.value.total_outstanding)}`],
        ['Collection Rate', `${reportData.value.collection_rate}%`],
        ['', ''],
        ['Property Performance Breakdown', ''],
        ['Property', 'Invoiced', 'Collected', 'Outstanding']
      ]

      reportData.value.by_property.forEach((item: any) => {
        rows.push([
          item.property_name,
          `Rp ${formatCurrency(item.invoiced)}`,
          `Rp ${formatCurrency(item.collected)}`,
          `Rp ${formatCurrency(item.outstanding)}`
        ])
      })

      // Convert rows to semicolon-separated values (extremely standard for international Excel formats)
      const csvContent = rows
        .map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(';'))
        .join('\n')

      // UTF-8 BOM so Excel opens it with correct encoding instantly
      const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      
      const periodStr = selectedMonth.value !== '' 
        ? `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}`
        : `${selectedYear.value}`
      
      link.setAttribute('href', url)
      link.setAttribute('download', `financial-report-${periodStr}.csv`)
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }

    function formatCurrency(val: number): string {
      return val.toLocaleString('id-ID')
    }

    watch([selectedPropertyId, selectedYear, selectedMonth], () => { loadData() })

    onMounted(() => { fetchProperties(); loadData() })

    return { properties, propertyError, reportData, isLoading, isExporting, error, selectedPropertyId, selectedYear, selectedMonth, yearsList, monthsList, trendChartData, chartOptions, handleExport, handleExportCSV, formatCurrency }
  }
})
</script>

<style scoped>
.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.kpi-card {
  background: #fff;
  border: 1px solid var(--g100);
  border-radius: 14px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 2px 8px rgba(26,23,18,0.04);
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,18,0.08); }

.kpi-icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.kpi-icon svg { width: 22px; height: 22px; }
.kpi-card--invoiced .kpi-icon { background: #ede9e1; color: var(--g700); }
.kpi-card--collected .kpi-icon { background: #d1fae5; color: #059669; }
.kpi-card--outstanding .kpi-icon { background: #fee2e2; color: #dc2626; }
.kpi-card--rate .kpi-icon { background: #fef3c7; color: #d97706; }

.kpi-info { display: flex; flex-direction: column; gap: 3px; }
.kpi-title { font-size: 0.72rem; font-weight: 600; color: var(--g400); text-transform: uppercase; letter-spacing: 0.04em; }
.kpi-value { font-size: 1.05rem; font-weight: 800; color: var(--g900); letter-spacing: -0.03em; }

.charts-section {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 16px;
}
@media (max-width: 1024px) { .charts-section { grid-template-columns: 1fr; } }

.chart-wrap { height: 300px; position: relative; margin-top: 12px; }
.chart-empty {
  height: 100%; position: relative; display: flex; align-items: center; justify-content: center;
  background: var(--g50); border-radius: 12px; border: 1px dashed var(--g200);
  overflow: hidden; padding: 24px;
}

.kpi-card--outstanding.kpi-card--critical {
  border-color: rgba(239, 68, 68, 0.4);
  background: rgba(239, 68, 68, 0.02);
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.04);
}
.kpi-card--outstanding.kpi-card--critical:hover {
  border-color: var(--status-red);
  background: rgba(239, 68, 68, 0.03);
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.08);
}

.skeleton-grid {
  position: absolute;
  top: 16px;
  left: 16px;
  right: 16px;
  bottom: 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  pointer-events: none;
}

.skeleton-grid-line {
  width: 100%;
  height: 1px;
  background: var(--g100);
}

.skeleton-bars {
  position: absolute;
  top: 32px;
  left: 24px;
  right: 24px;
  bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 8px;
  pointer-events: none;
}

.skeleton-bar-group {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 4px;
  max-width: 32px;
}

.skeleton-bar {
  width: 8px;
  border-radius: 4px 4px 0 0;
  opacity: 0.25;
}

.skeleton-bar--invoiced {
  background: #d4c09a;
}

.skeleton-bar--collected {
  background: #86efac;
}

.chart-empty-overlay {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(8px);
  padding: 18px 24px;
  border-radius: 12px;
  border: 1px solid var(--g100);
  box-shadow: 0 4px 12px rgba(26, 23, 18, 0.05);
}

.chart-empty-overlay .empty-icon {
  width: 30px;
  height: 30px;
  color: var(--g400);
}

.chart-empty-overlay .empty-text {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--g600);
}
</style>
