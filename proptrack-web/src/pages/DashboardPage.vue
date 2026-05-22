<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useProperty } from '@/composables/useProperty'
import { useContract } from '@/composables/useContract'
import { useInvoice } from '@/composables/useInvoice'
import { useTicket } from '@/composables/useTicket'
import { useReport } from '@/composables/useReport'
import { useTenant } from '@/composables/useTenant'
import { useNotification } from '@/composables/useNotification'
import type { Map as LeafletMap } from 'leaflet'

const { authStore, fetchProfile } = useAuth()
const router = useRouter()

const { fetchProperties, properties } = useProperty()
const { fetchTenants, tenants } = useTenant()
const { fetchContracts, contracts, downloadDocument: downloadContractDocument } = useContract()
const { fetchInvoices, invoices, downloadDocument: downloadInvoiceDocument } = useInvoice()
const { fetchTickets, tickets } = useTicket()
const { fetchSummary, reportData } = useReport()
const { fetchNotifications, notifications, markAsRead, markAllAsRead, handleNotificationClick, unreadCount } = useNotification()

const dashboardLoading = ref(true)
const mapContainer = ref<HTMLElement | null>(null)
let leafletMap: LeafletMap | null = null

const isOwnerOrAdmin = computed(() =>
  ['owner', 'admin'].some(role => authStore.user?.roles?.includes(role))
)
const isAgent = computed(() => authStore.user?.roles?.includes('agent'))
const isTenant = computed(() => authStore.user?.roles?.includes('tenant'))

const activeContracts = computed(() => contracts.value.filter(c => c.status === 'active'))

const tenantActiveContract = computed(() => activeContracts.value[0] || null)

const tenantNextInvoice = computed(() => {
  if (!isTenant.value) return null
  const unpaid = invoices.value.filter(inv => inv.status === 'unpaid' || inv.status === 'overdue')
  if (!unpaid.length) return null
  return [...unpaid].sort((a, b) => new Date(a.due_date).getTime() - new Date(b.due_date).getTime())[0]
})

const tenantLatestInvoice = computed(() => {
  if (!isTenant.value || !invoices.value.length) return null
  return [...invoices.value].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())[0]
})

const tenantOpenTicketsCount = computed(() => {
  if (!isTenant.value) return 0
  return tickets.value.filter(t => t.status === 'open' || t.status === 'in_progress').length
})

const leaseProgress = computed(() => {
  const contract = tenantActiveContract.value
  if (!contract) return 0
  const start = new Date(contract.start_date).getTime()
  const end = new Date(contract.end_date).getTime()
  const now = Date.now()
  if (now <= start) return 0
  if (now >= end) return 100
  return Math.round(((now - start) / (end - start)) * 100)
})

const leaseRemainingMonths = computed(() => {
  const contract = tenantActiveContract.value
  if (!contract) return ''
  const end = new Date(contract.end_date)
  const now = new Date()
  const yearsDiff = end.getFullYear() - now.getFullYear()
  const monthsDiff = end.getMonth() - now.getMonth()
  const totalMonths = yearsDiff * 12 + monthsDiff
  if (totalMonths <= 0) return 'Ends this month'
  if (totalMonths === 1) return '1 month left'
  return `${totalMonths} months left`
})

const featuredProperty = computed(() => {
  if (!properties.value.length) return null
  return properties.value.find(p => p.status === 'available') || properties.value[0]
})

const formatIDR = (value: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)

const formatDate = (dateStr: string) =>
  new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })

const formatTimeAgo = (dateStr: string) => {
  const date = new Date(dateStr)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days}d ago`
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

async function initMap() {
  if (!mapContainer.value) return
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')
  delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
  })
  const center: [number, number] = featuredProperty.value
    ? [featuredProperty.value.latitude, featuredProperty.value.longitude]
    : [-6.2088, 106.8456]

  leafletMap = L.map(mapContainer.value, { zoomControl: false, attributionControl: false }).setView(center, 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(leafletMap)

  const pinIcon = L.divIcon({
    className: '',
    html: `<div style="width:13px;height:13px;background:#e09c1a;border:2.5px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(30,22,8,0.28)"></div>`,
    iconSize: [13, 13], iconAnchor: [6, 6],
  })

  properties.value.forEach(p => {
    if (p.latitude && p.longitude) {
      L.marker([p.latitude, p.longitude], { icon: pinIcon })
        .addTo(leafletMap!)
        .bindPopup(`<strong style="font-family:'Outfit',sans-serif;font-size:13px">${p.name}</strong><br><small>${p.address}</small>`)
    }
  })
}

function destroyMap() {
  if (leafletMap) { leafletMap.remove(); leafletMap = null }
}

watch(properties, async (list) => {
  if (list.length && !leafletMap && !dashboardLoading.value) {
    await new Promise(r => setTimeout(r, 80))
    initMap()
  }
}, { immediate: false })

onMounted(async () => {
  try {
    await fetchProfile()
    const fetches: Promise<unknown>[] = []
    if (isOwnerOrAdmin.value) {
      fetches.push(
        fetchProperties(),
        fetchTenants(),
        fetchContracts(),
        fetchInvoices(),
        fetchTickets(),
        fetchNotifications(),
        fetchSummary(new Date().getFullYear())
      )
    } else if (isAgent.value) {
      fetches.push(
        fetchProperties(),
        fetchTickets(),
        fetchNotifications()
      )
    } else if (isTenant.value) {
      fetches.push(
        fetchInvoices(),
        fetchTickets(),
        fetchNotifications(),
        fetchContracts()
      )
    }
    await Promise.allSettled(fetches)
  } finally {
    dashboardLoading.value = false
    if (properties.value.length) {
      await new Promise(r => setTimeout(r, 120))
      await initMap()
    }
  }
})

onUnmounted(destroyMap)
</script>

<template>
  <div class="dash">
    <!-- Skeleton Loader -->
    <div v-if="dashboardLoading" class="dash__skeleton" aria-busy="true">
      <div class="skel-grid">
        <div class="skel-main">
          <div class="skel-kpis">
            <div class="skel-card shimmer" />
            <div class="skel-card shimmer" />
            <div class="skel-card shimmer" />
          </div>
          <div class="skel-widget shimmer" style="height: 200px; border-radius: 16px; margin-top: 20px;" />
          <div class="skel-widget shimmer" style="height: 240px; border-radius: 16px; margin-top: 20px;" />
        </div>
        <div class="skel-side">
          <div class="skel-widget shimmer" style="height: 240px; border-radius: 16px;" />
          <div class="skel-widget shimmer" style="height: 280px; border-radius: 16px; margin-top: 20px;" />
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Tenant Dashboard Layout -->
      <div v-if="isTenant" class="dash-grid tenant-dash-grid">
        <!-- ═══ Left Column: Tenant Main Content (2/3 width) ═══ -->
        <main class="dash-main">
          <header class="dash-header">
            <h1 class="dash-greeting">Welcome back, {{ authStore.user?.name }}</h1>
            <p class="dash-subtitle">Manage your home, view invoices, and track maintenance requests.</p>
          </header>

          <!-- KPI Metrics Row -->
          <section class="kpi-grid" aria-label="Key Performance Indicators">
            <!-- Next Rent Due -->
            <article class="kpi-card">
              <div v-if="tenantNextInvoice" class="kpi-card__badge kpi-badge--amber" :class="{ 'kpi-badge--negative': tenantNextInvoice.status === 'overdue' }">
                {{ tenantNextInvoice.status === 'overdue' ? 'Overdue' : 'Due Soon' }}
              </div>
              <div v-else class="kpi-card__badge kpi-badge--positive">Current</div>
              <p class="kpi-card__label">Next Rent Due</p>
              <h2 class="kpi-card__value tabular-nums">
                {{ tenantNextInvoice ? formatIDR(tenantNextInvoice.amount) : 'All Paid' }}
              </h2>
              <p class="kpi-card__context">
                {{ tenantNextInvoice ? `Due on ${formatDate(tenantNextInvoice.due_date)}` : 'No outstanding invoices' }}
              </p>
            </article>

            <!-- Latest Payment Status -->
            <article class="kpi-card">
              <div v-if="tenantLatestInvoice" class="kpi-card__badge" :class="tenantLatestInvoice.status === 'paid' ? 'kpi-badge--positive' : (tenantLatestInvoice.status === 'overdue' ? 'kpi-badge--negative' : 'kpi-badge--amber')">
                {{ tenantLatestInvoice.status }}
              </div>
              <div v-else class="kpi-card__badge kpi-badge--neutral">None</div>
              <p class="kpi-card__label">Latest Invoice</p>
              <h2 class="kpi-card__value tabular-nums">
                {{ tenantLatestInvoice ? formatIDR(tenantLatestInvoice.amount) : 'Rp 0' }}
              </h2>
              <p class="kpi-card__context">
                {{ tenantLatestInvoice ? `Billing month: ${tenantLatestInvoice.billing_month}` : 'No invoice record' }}
              </p>
            </article>

            <!-- Open Support Tickets -->
            <article class="kpi-card">
              <div class="kpi-card__badge" :class="tenantOpenTicketsCount > 0 ? 'kpi-badge--amber' : 'kpi-badge--neutral'">
                {{ tenantOpenTicketsCount > 0 ? 'Action Needed' : 'All Clear' }}
              </div>
              <p class="kpi-card__label">Active Tickets</p>
              <h2 class="kpi-card__value tabular-nums">{{ tenantOpenTicketsCount }}</h2>
              <p class="kpi-card__context">open & in progress</p>
            </article>
          </section>

          <!-- My Lease Agreement Card -->
          <section class="dash-widget widget--lease" aria-labelledby="widget-lease-title">
            <div class="dash-widget__header">
              <h2 id="widget-lease-title" class="dash-widget__title">My Lease Agreement</h2>
              <span class="dash-widget__count" :class="tenantActiveContract ? 'badge--green' : 'badge--gray'">
                {{ tenantActiveContract ? 'Active Lease' : 'No Contract' }}
              </span>
            </div>

            <div v-if="!tenantActiveContract" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
              <p class="widget-empty__text">No active lease agreement found. Contact your property owner to register a contract.</p>
            </div>
            <div v-else class="lease-detail-card">
              <div class="lease-header-info">
                <div class="lease-property-meta">
                  <h3 class="lease-property-name">{{ tenantActiveContract.property?.name }}</h3>
                  <p class="lease-property-address">{{ tenantActiveContract.property?.address }}</p>
                </div>
                <button @click="downloadContractDocument(tenantActiveContract.id)" class="btn-primary btn-sm btn-download-contract">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px; margin-right:6px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                  </svg>
                  Download PDF
                </button>
              </div>

              <div class="lease-specs-grid">
                <div class="spec-item">
                  <span class="spec-label">Monthly Rent</span>
                  <span class="spec-value">{{ formatIDR(tenantActiveContract.monthly_rent) }}</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Deposit Amount</span>
                  <span class="spec-value">{{ formatIDR(tenantActiveContract.deposit_amount) }}</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Billing Date</span>
                  <span class="spec-value">Day {{ tenantActiveContract.billing_date }} of every month</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Contract Period</span>
                  <span class="spec-value">{{ formatDate(tenantActiveContract.start_date) }} - {{ formatDate(tenantActiveContract.end_date) }}</span>
                </div>
              </div>

              <!-- Interactive Progress Slider Bar -->
              <div class="lease-progress-wrap">
                <div class="progress-labels">
                  <span class="progress-label-start">Start: {{ formatDate(tenantActiveContract.start_date) }}</span>
                  <span class="progress-label-remaining">{{ leaseRemainingMonths }}</span>
                  <span class="progress-label-end">End: {{ formatDate(tenantActiveContract.end_date) }}</span>
                </div>
                <div class="progress-track" title="Lease Period Progress">
                  <div class="progress-fill" :style="{ width: `${leaseProgress}%` }">
                    <span class="progress-tooltip">{{ leaseProgress }}% elapsed</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Open Tickets Tracker -->
          <section class="dash-widget widget--tickets" aria-labelledby="widget-tickets-title">
            <div class="dash-widget__header">
              <h2 id="widget-tickets-title" class="dash-widget__title">Active Maintenance & Support Tickets</h2>
              <button @click="router.push('/tickets/new')" class="btn-primary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px; height:12px; margin-right:4px;">
                  <path d="M12 5v14M5 12h14"/>
                </svg>
                File a ticket
              </button>
            </div>

            <div v-if="tickets.length === 0" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <p class="widget-empty__text">No active support tickets found. If you have any repair requests or issues, submit one now.</p>
            </div>
            <div v-else class="tickets-timeline">
              <div v-for="t in tickets.slice(0, 3)" :key="t.id" class="ticket-timeline-card" @click="router.push(`/tickets/${t.id}`)">
                <div class="ticket-timeline-header">
                  <span class="ticket-timeline-num">{{ t.ticket_number }}</span>
                  <span class="badge" :class="`badge--${t.priority === 'high' ? 'red' : (t.priority === 'medium' ? 'amber' : 'gray')}`">
                    {{ t.priority }}
                  </span>
                  <span class="badge" :class="{
                    'badge--gray': t.status === 'open',
                    'badge--amber': t.status === 'in_progress',
                    'badge--green': t.status === 'resolved',
                    'badge--indigo': t.status === 'closed',
                  }">
                    {{ t.status.replace('_', ' ') }}
                  </span>
                </div>
                <div class="ticket-timeline-body">
                  <h4 class="ticket-timeline-title">{{ t.title }}</h4>
                  <p class="ticket-timeline-category">Category: <strong>{{ t.category }}</strong></p>
                </div>
                <div class="ticket-timeline-footer">
                  <span class="ticket-timeline-time">Updated {{ formatTimeAgo(t.updated_at) }}</span>
                  <span class="ticket-timeline-link">View Thread &rarr;</span>
                </div>
              </div>
            </div>
          </section>
        </main>

        <!-- ═══ Right Column: Tenant Supporting Content (1/3 width) ═══ -->
        <aside class="dash-supporting">
          <!-- Billing & Payments Timeline Widget -->
          <section class="supporting-card supporting-card--billing">
            <header class="supporting-card__head">
              <h2 class="supporting-card__title">Billing & Payments</h2>
            </header>
            <p class="supporting-card__sub">Quick access to pay outstanding bills and download past statements.</p>

            <div v-if="invoices.length === 0" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <rect x="3" y="4" width="18" height="16" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              <p class="widget-empty__text">No invoice records found.</p>
            </div>
            <div v-else class="billing-timeline">
              <div v-for="inv in invoices.slice(0, 4)" :key="inv.id" class="billing-timeline-item">
                <div class="billing-timeline-info">
                  <div class="billing-timeline-header">
                    <span class="invoice-number">{{ inv.invoice_number }}</span>
                    <span class="badge" :class="{
                      'badge--green': inv.status === 'paid',
                      'badge--red': inv.status === 'overdue',
                      'badge--amber': inv.status === 'unpaid',
                    }">{{ inv.status }}</span>
                  </div>
                  <div class="invoice-meta-sub">
                    <span>Month: {{ inv.billing_month }}</span>
                    <span class="invoice-timeline-amount">{{ formatIDR(inv.amount) }}</span>
                  </div>
                </div>
                <div class="billing-timeline-action">
                  <button v-if="inv.status === 'unpaid' || inv.status === 'overdue'" 
                    @click="router.push(`/payments/${inv.id}`)" 
                    class="btn-primary btn-sm btn-pay-timeline">
                    Pay Now
                  </button>
                  <button v-else-if="inv.status === 'paid'" 
                    @click="downloadInvoiceDocument(inv.id, inv.invoice_number)" 
                    class="btn-ghost btn-sm btn-download-timeline"
                    title="Download Receipt">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Latest Notifications Feed Widget -->
          <section class="supporting-card widget--notifications" aria-labelledby="widget-notifications-title">
            <div class="dash-widget__header" style="margin-bottom: 8px; padding-bottom: 8px;">
              <h2 id="widget-notifications-title" class="supporting-card__title">Latest Notifications</h2>
              <button v-if="unreadCount > 0" @click="markAllAsRead" class="btn-link">Mark all as read</button>
            </div>

            <div v-if="notifications.length === 0" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              </svg>
              <p class="widget-empty__text">You are all caught up!</p>
            </div>
            <div v-else class="notifications-feed">
              <div v-for="n in notifications.slice(0, 5)" :key="n.id" 
                class="notification-item" 
                :class="{ 'notification-item--unread': n.read_at === null }"
                @click="handleNotificationClick(n)">
                <span class="notification-dot" :class="`notification-dot--${n.type}`" />
                <div class="notification-body">
                  <p class="notification-text">{{ n.message || n.title }}</p>
                  <span class="notification-time">{{ formatTimeAgo(n.created_at) }}</span>
                </div>
                <button v-if="n.read_at === null" @click.stop="markAsRead(n.id)" class="mark-read-btn" title="Mark as read">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                </button>
              </div>
            </div>
          </section>
        </aside>
      </div>

      <!-- Traditional Owner/Agent Dashboard Layout -->
      <div v-else class="dash-grid">
        <!-- ═══ Left Column: Actionable Data & Lists (2/3 width) ═══ -->
        <main class="dash-main">
          <header class="dash-header">
            <h1 class="dash-greeting">Welcome back, {{ authStore.user?.name }}</h1>
            <p class="dash-subtitle">Here is a quick overview of your portfolio activity.</p>
          </header>

          <!-- KPI Metrics Row -->
          <section class="kpi-grid" aria-label="Key Performance Indicators">
            <!-- Metric 1: Properties -->
            <article class="kpi-card">
              <div class="kpi-card__badge kpi-badge--positive">+1 this month</div>
              <p class="kpi-card__label">Total Portfolio</p>
              <h2 class="kpi-card__value tabular-nums">{{ properties.length }}</h2>
              <p class="kpi-card__context">portfolio lifetime</p>
            </article>

            <!-- Metric 2: Revenue -->
            <article class="kpi-card">
              <div class="kpi-card__badge kpi-badge--neutral">Stable</div>
              <p class="kpi-card__label">Collected Revenue</p>
              <h2 class="kpi-card__value tabular-nums">{{ reportData ? formatIDR(reportData.total_collected) : 'Rp 0' }}</h2>
              <p class="kpi-card__context">current month</p>
            </article>

            <!-- Metric 3: Active Leases -->
            <article class="kpi-card">
              <div class="kpi-card__badge kpi-badge--amber">92% occupancy</div>
              <p class="kpi-card__label">Active Leases</p>
              <h2 class="kpi-card__value tabular-nums">{{ activeContracts.length }}</h2>
              <p class="kpi-card__context">current billing cycle</p>
            </article>
          </section>

          <!-- Active Contracts List Widget -->
          <section class="dash-widget widget--contracts" aria-labelledby="widget-contracts-title">
            <div class="dash-widget__header">
              <h2 id="widget-contracts-title" class="dash-widget__title">Contract Status</h2>
              <span class="dash-widget__count">{{ activeContracts.length }} Active</span>
            </div>

            <div v-if="activeContracts.length === 0" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
              <p class="widget-empty__text">No active rental contracts. Create a new lease agreement to start tracking.</p>
            </div>
            <div v-else class="contracts-list">
              <div v-for="c in activeContracts" :key="c.id" class="contract-strip">
                <div class="contract-strip__tenant">
                  <div class="tenant-avatar">
                    {{ c.tenant?.name?.[0]?.toUpperCase() || 'T' }}
                  </div>
                  <div>
                    <h3 class="tenant-name">{{ c.tenant?.name }}</h3>
                  </div>
                </div>
                <div class="contract-strip__property">
                  <span class="strip-label">Property</span>
                  <span class="strip-value">{{ c.property?.name }}</span>
                </div>
                <div class="contract-strip__dates">
                  <span class="strip-label">Lease Term</span>
                  <span class="strip-value">{{ formatDate(c.start_date) }} - {{ formatDate(c.end_date) }}</span>
                </div>
                <div class="contract-strip__billing">
                  <span class="badge badge--amber">Day {{ c.billing_date }}</span>
                  <span class="strip-label" style="font-size: 0.65rem; margin-top: 4px;">Billing Day</span>
                </div>
                <div class="contract-strip__action">
                  <button @click="router.push(`/contracts/${c.id}`)" class="btn-ghost btn-sm">Manage</button>
                </div>
              </div>
            </div>
          </section>

          <!-- Latest Notifications Feed Widget -->
          <section class="dash-widget widget--notifications" aria-labelledby="widget-notifications-title">
            <div class="dash-widget__header">
              <h2 id="widget-notifications-title" class="dash-widget__title">Latest Notifications</h2>
              <button v-if="unreadCount > 0" @click="markAllAsRead" class="btn-link">Mark all as read</button>
            </div>

            <div v-if="notifications.length === 0" class="widget-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="widget-empty__icon">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              </svg>
              <p class="widget-empty__text">You are all caught up! No recent notifications found.</p>
            </div>
            <div v-else class="notifications-feed">
              <div v-for="n in notifications.slice(0, 5)" :key="n.id" 
                class="notification-item" 
                :class="{ 'notification-item--unread': n.read_at === null }"
                @click="handleNotificationClick(n)">
                <span class="notification-dot" :class="`notification-dot--${n.type}`" />
                <div class="notification-body">
                  <p class="notification-text">{{ n.message || n.title }}</p>
                  <span class="notification-time">{{ formatTimeAgo(n.created_at) }}</span>
                </div>
                <button v-if="n.read_at === null" @click.stop="markAsRead(n.id)" class="mark-read-btn" title="Mark as read">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                </button>
              </div>
            </div>
          </section>
        </main>

        <!-- ═══ Right Column: Supporting Overview Cards (1/3 width) ═══ -->
        <aside class="dash-supporting">
          <!-- Scaled Down Supporting Map -->
          <section class="supporting-card supporting-card--map">
            <header class="supporting-card__head">
              <h2 class="supporting-card__title">Mapped Locations</h2>
            </header>
            <div class="map-container-wrap">
              <div ref="mapContainer" class="map-canvas-small"></div>
              <div class="map-badge tabular-nums">
                {{ properties.length }} properties
              </div>
            </div>
          </section>

          <!-- Featured Property Showcase Card -->
          <section class="supporting-card supporting-card--property">
            <header class="supporting-card__head">
              <h2 class="supporting-card__title">
                <template v-if="featuredProperty">{{ featuredProperty.name }}</template>
                <template v-else>Property Spotlight</template>
              </h2>
              <button v-if="featuredProperty" class="heart-btn" title="Mark as favourite" aria-label="Mark as favourite">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
              </button>
            </header>

            <div class="property-spotlight">
              <!-- Visual Content -->
              <div class="spotlight-photo-box">
                <template v-if="featuredProperty && featuredProperty.photos.length">
                  <img :src="featuredProperty.photos[0].url" :alt="`Photo of ${featuredProperty.name}`" class="spotlight-img" />
                </template>
                <template v-else>
                  <div class="spotlight-empty-state">
                    <!-- Premium Custom SVG Illustration -->
                    <svg viewBox="0 0 160 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="spotlight-svg">
                      <circle cx="80" cy="60" r="48" fill="#f8f6f2" />
                      <circle cx="80" cy="60" r="36" fill="#f1ede7" />
                      <rect x="52" y="38" width="56" height="44" rx="6" fill="#fff" stroke="#e2ddd6" stroke-width="1.5" />
                      <circle cx="68" cy="50" r="4" fill="#f5dbb5" />
                      <path d="M54 74L72 58L84 68L96 54L106 74H54Z" fill="#e2ddd6" opacity="0.6" />
                      <g transform="translate(92, 64)">
                        <circle cx="14" cy="14" r="14" fill="#1a1712" />
                        <path d="M10 14H18M14 10V18" stroke="#fff" stroke-width="1.8" stroke-linecap="round" />
                      </g>
                    </svg>
                    <h3 class="spotlight-empty__heading">Capture Your Property's Best Side</h3>
                    <p class="spotlight-empty__text">High-quality photos increase listing visibility and attract potential tenants up to 40% faster. Upload your showcase photo now.</p>
                    <button v-if="featuredProperty && isOwnerOrAdmin" @click="router.push(`/properties/${featuredProperty.id}`)" class="btn-primary btn-sm" style="margin-top: 4px;">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px; height:12px; margin-right:4px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                      </svg>
                      Upload photo
                    </button>
                  </div>
                </template>
              </div>

              <!-- Property description -->
              <div v-if="featuredProperty" class="spotlight-info">
                <p class="spotlight-address">{{ featuredProperty.address }}</p>
                <div class="spotlight-meta">
                  <span class="status-dot" :class="`status-dot--${featuredProperty.status}`"></span>
                  <span class="spotlight-type">{{ featuredProperty.type }}</span>
                  <span class="spotlight-price">{{ formatIDR(featuredProperty.monthly_price) }}<span style="font-size:0.65rem;font-weight:400;color:var(--g400)">/mo</span></span>
                </div>
              </div>
              <div v-else class="spotlight-info">
                <p class="spotlight-address" style="color:var(--g300);font-style:italic">No properties found.</p>
              </div>
            </div>
          </section>

          <!-- Tenants Occupancy Donut Gauge Card -->
          <section class="supporting-card supporting-card--tenants">
            <header class="supporting-card__head">
              <h2 class="supporting-card__title">
                <span v-if="isOwnerOrAdmin || isAgent">Tenants Overview</span>
                <span v-else>My Activity</span>
              </h2>
              <div class="avatar-stack" v-if="(isOwnerOrAdmin || isAgent) && tenants.length">
                <div v-for="(t, i) in tenants.slice(0, 3)" :key="t.id" class="avatar-stack__item" :style="{ zIndex: 3 - i }">
                  {{ t.name?.[0]?.toUpperCase() || '?' }}
                </div>
              </div>
            </header>

            <p class="supporting-card__sub">
              <span v-if="isOwnerOrAdmin || isAgent">Active tenant community across all properties.</span>
              <span v-else>Your invoices and support tickets.</span>
            </p>

            <div class="gauge-area" aria-hidden="true">
              <svg class="gauge-svg" viewBox="0 0 120 70">
                <path d="M 10 65 A 50 50 0 0 1 110 65" fill="none" stroke="#f0ede7" stroke-width="10" stroke-linecap="round"/>
                <path d="M 10 65 A 50 50 0 0 1 110 65" fill="none" stroke="#e09c1a" stroke-width="10" stroke-linecap="round"
                  :stroke-dasharray="`${Math.min((isOwnerOrAdmin || isAgent ? tenants.length : invoices.length) / 20, 1) * 157.08} 157.08`"/>
              </svg>
              <div class="gauge-label">
                <span class="gauge-label__value tabular-nums">
                  <span v-if="isOwnerOrAdmin || isAgent">{{ tenants.length }}</span>
                  <span v-else>{{ invoices.length }}</span>
                </span>
                <span class="gauge-label__unit">
                  <span v-if="isOwnerOrAdmin || isAgent">tenants registered</span>
                  <span v-else>invoices loaded</span>
                </span>
              </div>
            </div>

            <div class="gauge-actions">
              <button v-if="isOwnerOrAdmin" @click="router.push('/tenants/new')" class="btn-primary btn-sm">Register tenant</button>
              <button v-else-if="isTenant" @click="router.push('/tickets/create')" class="btn-primary btn-sm">File a ticket</button>
              <button v-if="isOwnerOrAdmin || isAgent" @click="router.push('/tenants')" class="btn-ghost btn-sm">View all</button>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Main Dashboard Outer block */
.dash {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: #eaece7;
}

/* Skeleton Loading Structure */
.skel-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  padding: 28px 32px;
}

@media (max-width: 1024px) {
  .skel-grid {
    grid-template-columns: 1fr;
    padding: 16px;
    gap: 16px;
  }
}

.skel-main, .skel-side {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.skel-kpis {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.skel-card {
  height: 120px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--g100);
}

.skel-widget {
  background: #fff;
  border: 1px solid var(--g100);
}

/* Two-column Layout Grid */
.dash-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  padding: 28px 32px;
  align-items: start;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
  box-sizing: border-box;
}

@media (max-width: 1024px) {
  .dash-grid {
    grid-template-columns: 1fr;
    padding: 16px;
    gap: 16px;
  }
}

.dash-main {
  display: flex;
  flex-direction: column;
  gap: 24px;
  min-width: 0;
}

.dash-supporting {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header Greeting */
.dash-header {
  margin-bottom: 4px;
}

.dash-greeting {
  font-size: 1.55rem;
  font-weight: 800;
  color: var(--g900);
  letter-spacing: -0.03em;
  margin: 0;
  line-height: 1.25;
}

.dash-subtitle {
  font-size: 0.8125rem;
  color: var(--g500);
  margin: 5px 0 0;
}

/* KPI Cards Layout */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

@media (max-width: 768px) {
  .kpi-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

.kpi-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--g100);
  padding: 20px;
  display: flex;
  flex-direction: column;
  position: relative;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
  animation: slideUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) var(--kpi-delay, 0ms) both;
}

.kpi-card:nth-child(1) { --kpi-delay: 0ms; }
.kpi-card:nth-child(2) { --kpi-delay: 60ms; }
.kpi-card:nth-child(3) { --kpi-delay: 120ms; }

@keyframes slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.kpi-card__badge {
  align-self: flex-start;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.kpi-badge--positive {
  background: rgba(34, 197, 94, 0.1);
  color: #15803d;
}

.kpi-badge--neutral {
  background: var(--g50);
  color: var(--g600);
}

.kpi-badge--amber {
  background: rgba(224, 156, 26, 0.1);
  color: #92640a;
}

.kpi-card__label {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--g500);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.kpi-card__value {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--g900);
  margin: 6px 0;
  letter-spacing: -0.04em;
  line-height: 1.1;
}

.kpi-card__context {
  font-size: 0.7rem;
  color: var(--g400);
  margin: 0;
}

/* Common Widget Box */
.dash-widget {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--g100);
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
}

.dash-widget__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--g50);
  padding-bottom: 12px;
}

.dash-widget__title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
}

.dash-widget__count {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--g600);
  background: var(--g50);
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

/* Widget Empty State */
.widget-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 24px;
  text-align: center;
  background: var(--g50);
  border-radius: 12px;
  border: 1px dashed var(--g200);
}

.widget-empty__icon {
  width: 28px;
  height: 28px;
  color: var(--g300);
  margin-bottom: 8px;
}

.widget-empty__text {
  font-size: 0.75rem;
  color: var(--g400);
  margin: 0;
  line-height: 1.5;
  max-width: 280px;
  text-wrap: pretty;
}

/* Contract Strip Styling */
.contracts-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.contract-strip {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.2fr 0.8fr auto;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 12px;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}

.contract-strip:hover {
  border-color: var(--g200);
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}

@media (max-width: 768px) {
  .contract-strip {
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 16px;
  }
}

.contract-strip__tenant {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tenant-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--g200);
  color: var(--g700);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.tenant-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--g900);
  margin: 0;
}

.tenant-email {
  font-size: 0.6875rem;
  color: var(--g400);
  margin: 0;
}

.strip-label {
  display: block;
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--g400);
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin-bottom: 2px;
}

.strip-value {
  display: block;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--g700);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Notifications Feed Styling */
.notifications-feed {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--g50);
  border: 1px solid transparent;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
  cursor: pointer;
  position: relative;
}

.notification-item:hover {
  background: #fff;
  border-color: var(--g100);
  box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}

.notification-item--unread {
  background: rgba(224, 156, 26, 0.03);
  border-color: rgba(224, 156, 26, 0.15);
}

.notification-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}

.notification-dot--new_ticket,
.notification-dot--ticket_status_changed {
  background: var(--status-red);
}

.notification-dot--invoice_created,
.notification-dot--invoice_due,
.notification-dot--payment_confirmed {
  background: var(--status-green);
}

.notification-dot--contract_expiring {
  background: var(--amber);
}

.notification-body {
  flex: 1;
  min-width: 0;
}

.notification-text {
  font-size: 0.78rem;
  color: var(--g700);
  margin: 0;
  line-height: 1.45;
}

.notification-time {
  font-size: 0.6875rem;
  color: var(--g400);
  margin-top: 3px;
  display: inline-block;
}

.mark-read-btn {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 1px solid var(--g200);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--g500);
  transition: background 0.12s, color 0.12s, border-color 0.12s;
  align-self: center;
  flex-shrink: 0;
}

.mark-read-btn:hover {
  background: var(--g900);
  color: #fff;
  border-color: var(--g900);
}

.mark-read-btn svg {
  width: 9px;
  height: 9px;
}

.btn-link {
  background: none;
  border: none;
  color: var(--amber);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  font-family: inherit;
}

.btn-link:hover {
  text-decoration: underline;
}

/* Supporting Cards Styling */
.supporting-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--g100);
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
}

.supporting-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.supporting-card__title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
}

.supporting-card__sub {
  font-size: 0.75rem;
  color: var(--g400);
  margin: 0 0 14px;
  line-height: 1.5;
}

/* Compact Map Card elements */
.map-container-wrap {
  position: relative;
  height: 200px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--g100);
}

.map-canvas-small {
  width: 100%;
  height: 100%;
  background: var(--g50);
}

.map-badge {
  position: absolute;
  bottom: 12px;
  left: 12px;
  background: var(--g900);
  color: #fff;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.65rem;
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
  z-index: 400;
}

/* Spotlight Photo Empty State */
.property-spotlight {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.spotlight-photo-box {
  height: 200px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--g100);
  background: var(--g50);
  position: relative;
}

.spotlight-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.spotlight-photo-box:hover .spotlight-img {
  transform: scale(1.02);
}

.spotlight-empty-state {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 16px;
  text-align: center;
  box-sizing: border-box;
}

.spotlight-svg {
  width: 100px;
  height: 65px;
  margin-bottom: 4px;
}

.spotlight-empty__heading {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0 0 3px;
}

.spotlight-empty__text {
  font-size: 0.65rem;
  color: var(--g400);
  margin: 0 0 8px;
  line-height: 1.4;
  max-width: 240px;
  text-wrap: pretty;
}

.spotlight-info {
  padding-top: 2px;
}

.spotlight-address {
  font-size: 0.78rem;
  color: var(--g600);
  margin: 0 0 6px;
  line-height: 1.45;
}

.spotlight-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.72rem;
  color: var(--g400);
}

.spotlight-type {
  text-transform: capitalize;
  font-weight: 500;
  color: var(--g600);
}

.spotlight-price {
  font-weight: 700;
  color: var(--amber);
  margin-left: auto;
  font-size: 0.8125rem;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.status-dot--available   { background: #22c55e; }
.status-dot--occupied    { background: #ef4444; }
.status-dot--maintenance { background: var(--amber); }

.heart-btn {
  width: 24px;
  height: 24px;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--amber);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 6px;
  transition: transform 0.15s;
}

.heart-btn:hover { transform: scale(1.15); }
.heart-btn:active { transform: scale(0.92); }
.heart-btn svg { width: 14px; height: 14px; }

/* Tenants occupancy donut gauge */
.gauge-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 12px 0 16px;
}

.gauge-svg {
  width: 120px;
  height: 70px;
  overflow: visible;
}

.gauge-label {
  text-align: center;
  margin-top: -2px;
}

.gauge-label__value {
  display: block;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--g900);
  letter-spacing: -0.05em;
  line-height: 1;
}

.gauge-label__unit {
  font-size: 0.65rem;
  color: var(--g400);
  margin-top: 2px;
  display: block;
}

.avatar-stack {
  display: flex;
  flex-direction: row-reverse;
}

.avatar-stack__item {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--g200);
  border: 2px solid #fff;
  margin-left: -6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.55rem;
  font-weight: 700;
  color: var(--g600);
}

.gauge-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 0.75rem;
  border-radius: 8px;
}

/* Tenant Specific Dashboard Styles */
.tenant-dash-grid {
  /* Shares general grid layout with owner dashboard */
}

/* Lease widget styling */
.lease-detail-card {
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.lease-header-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.lease-property-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
}

.lease-property-address {
  font-size: 0.8rem;
  color: var(--g500);
  margin: 4px 0 0;
}

.btn-download-contract {
  display: flex;
  align-items: center;
  font-family: var(--font-sans);
  background: var(--g900);
  color: #fff;
  border: none;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}

.btn-download-contract:hover {
  background: var(--g700);
  transform: translateY(-1px);
}

.btn-download-contract:active {
  transform: translateY(0);
}

.lease-specs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  border-top: 1px dashed var(--g200);
  border-bottom: 1px dashed var(--g200);
  padding: 16px 0;
}

@media (max-width: 768px) {
  .lease-specs-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
}

.spec-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.spec-label {
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--g400);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.spec-value {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--g800);
}

/* Lease progress tracker */
.lease-progress-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.72rem;
  color: var(--g500);
  font-weight: 500;
}

.progress-label-remaining {
  font-weight: 700;
  color: var(--amber);
  background: var(--amber-soft);
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.progress-track {
  height: 10px;
  background: var(--g200);
  border-radius: 999px;
  position: relative;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--amber);
  border-radius: 999px;
  position: relative;
  transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.progress-tooltip {
  position: absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.55rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  opacity: 0.9;
}

/* Tickets Timeline section */
.tickets-timeline {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

@media (max-width: 900px) {
  .tickets-timeline {
    grid-template-columns: 1fr;
  }
}

.ticket-timeline-card {
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  cursor: pointer;
  transition: transform 0.15s, background 0.15s, border-color 0.15s, box-shadow 0.15s;
}

.ticket-timeline-card:hover {
  background: #fff;
  border-color: var(--g200);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.ticket-timeline-card:active {
  transform: translateY(0);
}

.ticket-timeline-header {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.ticket-timeline-num {
  font-family: var(--font-sans);
  font-weight: 700;
  font-size: 0.72rem;
  color: #4338ca;
  background: rgba(99, 102, 241, 0.08);
  padding: 2px 6px;
  border-radius: 4px;
}

.ticket-timeline-body {
  flex: 1;
}

.ticket-timeline-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--g900);
  margin: 0;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ticket-timeline-category {
  font-size: 0.7rem;
  color: var(--g400);
  margin: 6px 0 0;
}

.ticket-timeline-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--g100);
  padding-top: 8px;
  margin-top: 4px;
}

.ticket-timeline-time {
  font-size: 0.65rem;
  color: var(--g400);
}

.ticket-timeline-link {
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--amber);
}

/* Billing timeline styling */
.billing-timeline {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.billing-timeline-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background: var(--g50);
  border: 1px solid var(--g100);
  border-radius: 10px;
  transition: background 0.15s, border-color 0.15s;
}

.billing-timeline-item:hover {
  background: #fff;
  border-color: var(--g200);
}

.billing-timeline-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.billing-timeline-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.invoice-number {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--g800);
}

.invoice-meta-sub {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.68rem;
  color: var(--g500);
}

.invoice-timeline-amount {
  font-weight: 700;
  color: var(--g900);
}

.btn-pay-timeline {
  background: var(--amber);
  color: #fff;
  border: none;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}

.btn-pay-timeline:hover {
  background: #c88812;
  transform: translateY(-1px);
}

.btn-download-timeline {
  background: #fff;
  border: 1px solid var(--g200);
  color: var(--g600);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.btn-download-timeline:hover {
  background: var(--g900);
  color: #fff;
  border-color: var(--g900);
}
</style>
