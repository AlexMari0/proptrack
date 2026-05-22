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
import type { Map as LeafletMap } from 'leaflet'

const { authStore, fetchProfile } = useAuth()
const router = useRouter()

const { fetchProperties, properties } = useProperty()
const { fetchTenants, tenants } = useTenant()
const { fetchContracts, contracts } = useContract()
const { fetchInvoices, invoices } = useInvoice()
const { fetchTickets, tickets } = useTicket()
const { fetchSummary, reportData } = useReport()

const dashboardLoading = ref(true)
const mapContainer = ref<HTMLElement | null>(null)
let leafletMap: LeafletMap | null = null

const isOwnerOrAdmin = computed(() =>
  ['owner', 'admin'].some(role => authStore.user?.roles?.includes(role))
)
const isAgent = computed(() => authStore.user?.roles?.includes('agent'))
const isTenant = computed(() => authStore.user?.roles?.includes('tenant'))

const activeContracts = computed(() => contracts.value.filter(c => c.status === 'active'))
const openTickets = computed(() => tickets.value.filter(t => t.status === 'open'))

const featuredProperty = computed(() => {
  if (!properties.value.length) return null
  return properties.value.find(p => p.status === 'available') || properties.value[0]
})

const formatIDR = (value: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)

const formatDate = (dateStr: string) =>
  new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })

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
  if (list.length && !leafletMap) {
    await new Promise(r => setTimeout(r, 80))
    initMap()
  }
}, { immediate: false })

onMounted(async () => {
  try {
    await fetchProfile()
    const fetches: Promise<unknown>[] = []
    if (isOwnerOrAdmin.value) {
      fetches.push(fetchProperties(), fetchTenants(), fetchContracts(), fetchInvoices(), fetchTickets(), fetchSummary(new Date().getFullYear()))
    } else if (isAgent.value) {
      fetches.push(fetchProperties(), fetchTickets())
    } else if (isTenant.value) {
      fetches.push(fetchInvoices(), fetchTickets())
    }
    await Promise.allSettled(fetches)
  } finally {
    dashboardLoading.value = false
  }
})

onUnmounted(destroyMap)
</script>

<template>
  <div class="dash">
    <!-- Skeleton -->
    <div v-if="dashboardLoading" class="dash__skeleton" aria-busy="true">
      <div class="skel-map shimmer"></div>
      <div class="skel-row">
        <div class="skel-card shimmer"></div>
        <div class="skel-card shimmer"></div>
        <div class="skel-card shimmer"></div>
      </div>
    </div>

    <template v-else>
      <!-- Map hero -->
      <section class="map-section" aria-label="Property map">
        <div ref="mapContainer" class="map-canvas"></div>

        <!-- Floating stat pills -->
        <div class="map-stats" aria-hidden="true">
          <div class="map-stat">
            <span class="map-stat__value tabular-nums">{{ properties.length }}</span>
            <span class="map-stat__label">Properties</span>
          </div>
          <div class="map-stat">
            <span class="map-stat__value tabular-nums">{{ reportData ? formatIDR(reportData.total_collected) : 'Rp 0' }}</span>
            <span class="map-stat__label">Collected revenue</span>
          </div>
          <div class="map-stat">
            <span class="map-stat__value tabular-nums">{{ activeContracts.length }}</span>
            <span class="map-stat__label">Active leases</span>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!properties.length && isOwnerOrAdmin" class="map-empty" role="status">
          <p class="map-empty__text">No properties plotted yet. Add your first to see it on the map.</p>
          <button @click="router.push('/properties/new')" class="btn-primary">Add first property</button>
        </div>
      </section>

      <!-- Bottom info row -->
      <div class="info-row">

        <!-- Card 1 — Location -->
        <article class="info-card info-card--location card-stagger-1">
          <header class="info-card__head">
            <h2 class="info-card__title">
              <template v-if="featuredProperty">{{ featuredProperty.name }}</template>
              <template v-else>Location overview</template>
            </h2>
            <button class="heart-btn" title="Mark as favourite" aria-label="Mark as favourite">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
            </button>
          </header>

          <template v-if="featuredProperty">
            <p class="info-card__address">{{ featuredProperty.address }}</p>
            <div class="info-card__meta">
              <span class="status-dot" :class="`status-dot--${featuredProperty.status}`"></span>
              <span class="info-card__type">{{ featuredProperty.type }}</span>
              <time class="info-card__date" :datetime="featuredProperty.created_at">{{ formatDate(featuredProperty.created_at) }}</time>
            </div>
          </template>
          <template v-else>
            <p class="info-card__address" style="color:var(--g300);font-style:italic">No properties yet.</p>
          </template>

          <div class="mini-stats">
            <div class="mini-stat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
              <div><p class="mini-stat__label">Active contracts</p><p class="mini-stat__value tabular-nums">{{ activeContracts.length }}</p></div>
            </div>
            <div class="mini-stat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <div><p class="mini-stat__label">Open tickets</p><p class="mini-stat__value tabular-nums">{{ openTickets.length }}</p></div>
            </div>
            <div class="mini-stat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              <div><p class="mini-stat__label">Revenue</p><p class="mini-stat__value tabular-nums">{{ reportData ? formatIDR(reportData.total_collected) : '—' }}</p></div>
            </div>
          </div>
        </article>

        <!-- Card 2 — Photo -->
        <div class="info-card info-card--photo card-stagger-2">
          <template v-if="featuredProperty && featuredProperty.photos.length">
            <img :src="featuredProperty.photos[0].url" :alt="`Photo of ${featuredProperty.name}`" class="property-photo" />
          </template>
          <template v-else>
            <div class="photo-placeholder">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;color:var(--g300)" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <p style="font-size:0.75rem;color:var(--g400);margin:0">No photo yet</p>
              <button v-if="featuredProperty && isOwnerOrAdmin" @click="router.push(`/properties/${featuredProperty.id}`)" class="btn-ghost" style="font-size:0.75rem;padding:6px 12px">Upload photo</button>
            </div>
          </template>
        </div>

        <!-- Card 3 — Tenants gauge -->
        <article class="info-card info-card--tenants card-stagger-3">
          <header class="info-card__head">
            <h2 class="info-card__title">
              <span v-if="isOwnerOrAdmin || isAgent">Tenants</span>
              <span v-else>My activity</span>
            </h2>
            <div class="avatar-stack" v-if="(isOwnerOrAdmin || isAgent) && tenants.length">
              <div v-for="(t, i) in tenants.slice(0, 3)" :key="t.id" class="avatar-stack__item" :style="{ zIndex: 3 - i }">{{ t.name?.[0]?.toUpperCase() || '?' }}</div>
            </div>
          </header>
          <p class="info-card__sub">
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
              <span class="gauge-label__unit"><span v-if="isOwnerOrAdmin || isAgent">tenants</span><span v-else>invoices</span></span>
            </div>
          </div>
          <div style="display:flex;gap:7px;flex-wrap:wrap">
            <button v-if="isOwnerOrAdmin" @click="router.push('/tenants/new')" class="btn-primary" style="font-size:0.75rem;padding:7px 13px">Register tenant</button>
            <button v-else-if="isTenant" @click="router.push('/tickets/create')" class="btn-primary" style="font-size:0.75rem;padding:7px 13px">File a ticket</button>
            <button v-if="isOwnerOrAdmin || isAgent" @click="router.push('/tenants')" class="btn-ghost" style="font-size:0.75rem;padding:7px 13px">View all</button>
          </div>
        </article>

      </div>
    </template>
  </div>
</template>

<style scoped>
/* Dashboard inner — fills the panel slot */
.dash {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

/* Skeleton */
.dash__skeleton { display: flex; flex-direction: column; flex: 1; }
.skel-map { flex: 1; min-height: 280px; margin: 0; border-radius: 0; }
.skel-row { display: grid; grid-template-columns: 1fr 0.9fr 0.85fr; min-height: 200px; gap: 0; }
.skel-card { margin: 20px; border-radius: 12px; }

/* Map */
.map-section {
  position: relative;
  flex: 1 1 0;
  min-height: 280px;
  overflow: hidden;
}

.map-canvas {
  width: 100%;
  height: 100%;
  min-height: 280px;
  background: var(--g100);
}

.map-stats {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  gap: 8px;
  pointer-events: none;
  z-index: var(--z-pills);
}

.map-stat {
  background: var(--g900);
  color: #fff;
  border-radius: 14px;
  padding: 12px 18px;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 100px;
  box-shadow: 0 8px 28px rgba(26,23,18,0.36), 0 2px 8px rgba(26,23,18,0.18);
  animation: pillFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) var(--pill-delay,0ms) both;
}
.map-stat:nth-child(1) { --pill-delay: 300ms; }
.map-stat:nth-child(2) { --pill-delay: 400ms; }
.map-stat:nth-child(3) { --pill-delay: 500ms; }

@keyframes pillFadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

.map-stat__value { font-size:1.35rem; font-weight:800; letter-spacing:-0.04em; line-height:1.1; white-space:nowrap; text-wrap:balance; }
.map-stat__label { font-size:0.65rem; color:var(--g400); margin-top:3px; text-align:center; }

.map-empty {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;
  background: rgba(255,255,255,0.72);
  backdrop-filter: blur(8px) saturate(1.4);
  border: 1px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
  z-index: var(--z-overlay);
}
.map-empty__text { font-size:0.8125rem; color:var(--g500); text-align:center; max-width:240px; line-height:1.55; text-wrap:pretty; margin:0; }

/* Info row */
.info-row {
  display: grid;
  grid-template-columns: 1fr 0.9fr 0.85fr;
  min-height: 210px;
  border-top: 1px solid var(--g100);
  flex-shrink: 0;
}

@keyframes slideUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
.card-stagger-1 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0ms both; }
.card-stagger-2 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 80ms both; }
.card-stagger-3 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 160ms both; }

.info-card {
  padding: 20px;
  border-right: 1px solid var(--g100);
  display: flex; flex-direction: column; gap: 10px;
}
.info-card:last-child { border-right: none; }
.info-card--location, .info-card--tenants { background: var(--g50); }
.info-card--photo { padding: 0; overflow: hidden; }

.info-card__head { display: flex; align-items: center; justify-content: space-between; }
.info-card__title { font-size:0.875rem; font-weight:700; color:var(--g900); margin:0; letter-spacing:-0.02em; text-wrap:balance; }
.info-card__address { font-size:0.775rem; color:var(--g500); margin:0; line-height:1.55; }
.info-card__meta { display:flex; align-items:center; gap:8px; font-size:0.72rem; color:var(--g400); }
.info-card__date { margin-left:auto; font-variant-numeric:tabular-nums; }
.info-card__type { text-transform:capitalize; font-weight:500; color:var(--g600); }
.info-card__sub { font-size:0.75rem; color:var(--g400); margin:0; line-height:1.55; text-wrap:pretty; }

.status-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.status-dot--available   { background: #22c55e; }
.status-dot--occupied    { background: #ef4444; }
.status-dot--maintenance { background: var(--amber); }

.heart-btn {
  width:28px; height:28px; border:none; background:none; cursor:pointer; color:var(--amber);
  display:flex; align-items:center; justify-content:center; padding:0; border-radius:6px;
  transition: transform 0.15s; 
}
.heart-btn:hover { transform:scale(1.18); }
.heart-btn:active { transform:scale(0.94); }
.heart-btn:focus-visible { outline:2px solid var(--amber); outline-offset:2px; }
.heart-btn svg { width:15px; height:15px; }

.mini-stats { display:flex; gap:16px; margin-top:auto; padding-top:4px; }
.mini-stat { display:flex; align-items:center; gap:8px; flex:1; }
.mini-stat svg { width:18px; height:18px; color:var(--g300); flex-shrink:0; }
.mini-stat__label { font-size:0.68rem; color:var(--g400); margin:0; }
.mini-stat__value { font-size:0.95rem; font-weight:700; color:var(--g900); margin:0; letter-spacing:-0.025em; }

.property-photo { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.4s ease; }
.info-card--photo:hover .property-photo { transform:scale(1.02); }
.photo-placeholder {
  width:100%; height:100%; min-height:180px;
  background: radial-gradient(ellipse at 30% 30%, var(--g200) 0%, var(--g100) 60%, var(--g50) 100%);
  display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:10px;
}

.gauge-area { display:flex; flex-direction:column; align-items:center; margin-top:auto; }
.gauge-svg { width:120px; height:70px; overflow:visible; }
.gauge-label { text-align:center; margin-top:-2px; }
.gauge-label__value { display:block; font-size:1.7rem; font-weight:800; color:var(--g900); letter-spacing:-0.05em; line-height:1; }
.gauge-label__unit { font-size:0.7rem; color:var(--g400); }

.avatar-stack { display:flex; flex-direction:row-reverse; }
.avatar-stack__item {
  width:24px; height:24px; border-radius:50%; background:var(--g200); border:2px solid var(--g50);
  margin-left:-7px; display:flex; align-items:center; justify-content:center;
  font-size:0.58rem; font-weight:700; color:var(--g600);
}
</style>
