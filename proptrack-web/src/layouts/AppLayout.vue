<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { useTicket } from '@/composables/useTicket'
import { useInvoice } from '@/composables/useInvoice'
import { useNotification } from '@/composables/useNotification'
import NavbarNotificationBell from '@/components/layout/NavbarNotificationBell.vue'
import AppLogo from '@/components/layout/AppLogo.vue'


const authStore = useAuthStore()
const { logout, loading: authLoading } = useAuth()

const { fetchTickets, tickets } = useTicket()
const { fetchInvoices, invoices } = useInvoice()
const { fetchNotifications, unreadCount } = useNotification()

const isOwnerOrAdmin = () =>
  ['owner', 'admin'].some(role => authStore.user?.roles?.includes(role))
const isAgent = () => authStore.user?.roles?.includes('agent')

const isExpanded = ref(localStorage.getItem('sidebar_expanded') === 'true')
const toggleSidebar = () => {
  isExpanded.value = !isExpanded.value
  localStorage.setItem('sidebar_expanded', isExpanded.value ? 'true' : 'false')
}

const isNotificationsOpen = ref(false)
const toggleNotifications = () => {
  isNotificationsOpen.value = !isNotificationsOpen.value
}

const openTicketsCount = computed(() => tickets.value.filter(t => t.status === 'open').length)
const unpaidInvoicesCount = computed(() => invoices.value.filter(i => i.status === 'unpaid' || i.status === 'overdue').length)

onMounted(async () => {
  if (authStore.user) {
    const fetches: Promise<unknown>[] = [fetchNotifications()]
    if (isOwnerOrAdmin()) {
      fetches.push(fetchTickets(), fetchInvoices())
    } else if (isAgent()) {
      fetches.push(fetchTickets())
    } else {
      fetches.push(fetchTickets(), fetchInvoices())
    }
    await Promise.allSettled(fetches)
  }
})
</script>

<template>
  <div class="shell">
    <!-- ═══ Icon/Expanded sidebar ═════════════════════════════════════════════ -->
    <aside class="sidebar" :class="{ 'sidebar--expanded': isExpanded }" aria-label="Site navigation">
      <div class="sidebar__top">
        <div class="sidebar__logo">
          <RouterLink to="/dashboard" class="logo-link" aria-label="PropTrack home">
            <AppLogo :size="isExpanded ? 36 : 32" class="logo-mark-component" />
            <span class="logo-text">PropTrack</span>
          </RouterLink>
        </div>
      </div>

      <nav class="sidebar__nav" aria-label="Main">
        <RouterLink to="/dashboard" class="sidebar__link" aria-label="Dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span class="sidebar__label">Dashboard</span>
          <span class="sidebar__tooltip">Dashboard</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin() || isAgent()" to="/properties" class="sidebar__link" aria-label="Properties">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M8 10h.01M8 14h.01M16 10h.01M16 14h.01"/></svg>
          <span class="sidebar__label">Properties</span>
          <span class="sidebar__tooltip">Properties</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin() || isAgent()" to="/tenants" class="sidebar__link" aria-label="Tenants">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span class="sidebar__label">Tenants</span>
          <span class="sidebar__tooltip">Tenants</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/contracts" class="sidebar__link" aria-label="Contracts">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
          <span class="sidebar__label">Contracts</span>
          <span class="sidebar__tooltip">Contracts</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/invoices" class="sidebar__link" aria-label="Invoices">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/></svg>
          <span class="sidebar__label">Invoices</span>
          <span v-if="unpaidInvoicesCount > 0" class="sidebar__badge">{{ unpaidInvoicesCount }}</span>
          <span class="sidebar__tooltip">Invoices</span>
        </RouterLink>
        <RouterLink to="/tickets" class="sidebar__link" aria-label="Support tickets">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
          <span class="sidebar__label">Tickets</span>
          <span v-if="openTicketsCount > 0" class="sidebar__badge">{{ openTicketsCount }}</span>
          <span class="sidebar__tooltip">Tickets</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/reports/financial" class="sidebar__link" aria-label="Financial reports">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>
          <span class="sidebar__label">Reports</span>
          <span class="sidebar__tooltip">Reports</span>
        </RouterLink>
      </nav>

      <div class="sidebar__bottom">
        <button
          class="sidebar__link"
          :class="{ 'sidebar__link--active': isNotificationsOpen }"
          @click="toggleNotifications"
          aria-label="Notifications"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span class="sidebar__label">Notifications</span>
          <span v-if="unreadCount > 0" class="sidebar__badge">{{ unreadCount }}</span>
          <span class="sidebar__tooltip">Notifications</span>
        </button>
        <button class="sidebar__link sidebar__link--logout" @click="logout" :disabled="authLoading" aria-label="Sign out">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          <span class="sidebar__label">Sign out</span>
          <span class="sidebar__tooltip">Sign out</span>
        </button>
      </div>

      <!-- Teleported Notification Drawer -->
      <NavbarNotificationBell :isOpen="isNotificationsOpen" @close="isNotificationsOpen = false" />
    </aside>

    <!-- ═══ Right panel ═══════════════════════════════════════════════════════ -->
    <div class="panel">
      <!-- Top bar -->
      <header class="topbar" role="banner">
        <div class="topbar__left">
          <!-- Sidebar toggle button next to the user's name -->
          <button class="topbar__toggle" @click="toggleSidebar" :aria-label="isExpanded ? 'Collapse sidebar' : 'Expand sidebar'">
            <Transition name="icon-fade" mode="out-in">
              <svg v-if="isExpanded" key="collapse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
              </svg>
              <svg v-else key="expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </Transition>
          </button>
          <!-- Breadcrumb-style page label -->
          <p class="topbar__route">{{ authStore.user?.name }}</p>
        </div>
        <div class="topbar__right">
          <RouterLink to="/profile" class="topbar__avatar-link" aria-label="View profile">
            <div class="topbar__avatar" aria-hidden="true">
              {{ authStore.user?.name?.[0]?.toUpperCase() || 'U' }}
            </div>
          </RouterLink>
        </div>
      </header>

      <!-- Page content slot -->
      <main id="main-content" class="panel__main">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
/* ─── Shell — outer cream wrapper ────────────────────────────────────────────── */
.shell {
  display: flex;
  min-height: 100dvh;
  background: #eaece7;
  font-family: 'Outfit', var(--font-sans);
  padding: 0;
  gap: 0;
  position: relative;
}

/* Grain texture */
.shell::after {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: var(--z-toast);
  opacity: 0.026;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)'/%3E%3C/svg%3E");
  background-repeat: repeat;
}

/* ─── Sidebar ────────────────────────────────────────────────────────────────── */
.sidebar {
  position: sticky;
  top: 0;
  height: 100dvh;
  width: 64px;
  background: #fff;
  border-radius: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 18px 12px;
  gap: 2px;
  flex-shrink: 0;
  z-index: var(--z-sidebar);
  border-right: 1px solid var(--g100);
  transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded {
  width: 220px;
  align-items: stretch;
}

.sidebar__top {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  margin-bottom: 18px;
  gap: 12px;
  transition: flex-direction 0.25s, justify-content 0.25s, padding 0.25s;
}

.sidebar--expanded .sidebar__top {
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  padding: 0 4px;
}

.sidebar__logo {
  display: flex;
  justify-content: center;
}

.sidebar--expanded .sidebar__logo {
  justify-content: flex-start;
}

.logo-link {
  display: flex;
  align-items: center;
  text-decoration: none;
  gap: 0;
  transition: gap 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded .logo-link {
  gap: 12px;
}

.logo-mark-component {
  transition: transform 0.15s, width 0.3s cubic-bezier(0.16, 1, 0.3, 1), height 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  flex-shrink: 0;
}
.logo-mark-component:hover {
  transform: scale(1.06);
}

.logo-text {
  font-weight: 800;
  font-size: 1.15rem;
  color: var(--g900);
  letter-spacing: -0.02em;
  opacity: 0;
  max-width: 0;
  overflow: hidden;
  white-space: nowrap;
  /* Instant fade-out when collapsing to prevent horizontal layout squishing */
  transition: opacity 0.08s ease, max-width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded .logo-text {
  opacity: 1;
  max-width: 140px;
  /* Smooth delayed fade-in when expanding */
  transition: opacity 0.25s ease 0.05s, max-width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar__nav {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  flex: 1;
  width: 100%;
}

.sidebar--expanded .sidebar__nav {
  align-items: stretch;
}

.sidebar__link {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  color: var(--g400);
  transition: background 0.15s, color 0.15s, transform 0.12s, width 0.3s cubic-bezier(0.16, 1, 0.3, 1), padding 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
  border: none;
  background: transparent;
  text-decoration: none;
  box-sizing: border-box;
  position: relative;
  overflow: visible;
}

.sidebar--expanded .sidebar__link {
  width: 100%;
  justify-content: flex-start;
  padding: 0 12px;
}

.sidebar__link svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.sidebar__link:hover { background: var(--g50); color: var(--g700); }

.sidebar__link.router-link-active,
.sidebar__link--active { background: var(--g900); color: #fff; }

.sidebar__link:focus-visible {
  outline: 2px solid var(--amber);
  outline-offset: 2px;
}

.sidebar__link:active { transform: scale(0.98); }

.sidebar__link--logout:hover { background: #fef2f2; color: #dc2626; }

.sidebar__label {
  font-weight: 500;
  font-size: 0.875rem;
  opacity: 0;
  max-width: 0;
  overflow: hidden;
  white-space: nowrap;
  /* Instant fade-out when collapsing to prevent label wrap/squish */
  transition: opacity 0.08s ease, max-width 0.3s cubic-bezier(0.16, 1, 0.3, 1), margin-left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  margin-left: 0;
  color: inherit;
}

.sidebar--expanded .sidebar__label {
  opacity: 1;
  max-width: 140px;
  margin-left: 10px;
  /* Smooth delayed fade-in when expanding */
  transition: opacity 0.25s ease 0.05s, max-width 0.3s cubic-bezier(0.16, 1, 0.3, 1), margin-left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Sidebar Notification Badge */
.sidebar__badge {
  position: absolute;
  top: 4px;
  right: 4px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  background: var(--status-red, #dc2626);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
  pointer-events: none;
  z-index: 5;
  transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1), top 0.3s cubic-bezier(0.16, 1, 0.3, 1), transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded .sidebar__badge {
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
}

.sidebar__bottom {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  margin-top: auto;
  padding-bottom: 2px;
  width: 100%;
}

.sidebar--expanded .sidebar__bottom {
  align-items: stretch;
}


/* Sidebar toggle button (collapsible sidebar, in top header next to user name) */
.topbar__toggle {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  color: var(--g700);
  background: #fff;
  border: 1px solid var(--g100);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s, transform 0.12s, box-shadow 0.15s;
  flex-shrink: 0;
}
.topbar__toggle:hover {
  background: var(--g50);
  border-color: var(--g200);
  color: var(--g900);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
.topbar__toggle:active {
  transform: scale(0.95);
}
.topbar__toggle svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  stroke-width: 2.5;
  display: block;
}

/* Icon cross-fade transition */
.icon-fade-enter-active,
.icon-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.icon-fade-enter-from {
  opacity: 0;
  transform: scale(0.7) rotate(-35deg);
}
.icon-fade-leave-to {
  opacity: 0;
  transform: scale(0.7) rotate(35deg);
}

/* ─── Premium Custom CSS Tooltips ────────────────────────────────────────── */
.sidebar__tooltip {
  position: absolute;
  left: 54px; /* Renders to the right of the minimized link (40px wide + margin/gap) */
  top: 50%;
  transform: translateY(-50%) translateX(6px);
  background: var(--g900);
  color: #fff;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
  box-shadow: 0 4px 12px rgba(26, 23, 18, 0.16);
  z-index: 100;
}

/* Tooltip arrow pointing left */
.sidebar__tooltip::before {
  content: '';
  position: absolute;
  right: 100%;
  top: 50%;
  transform: translateY(-50%);
  border-width: 4px;
  border-style: solid;
  border-color: transparent var(--g900) transparent transparent;
}

/* Show custom tooltip on hover ONLY when the sidebar is collapsed (minimized) */
.sidebar:not(.sidebar--expanded) .sidebar__link:hover .sidebar__tooltip {
  opacity: 1;
  transform: translateY(-50%) translateX(0);
}

/* Hide tooltip completely when the sidebar is expanded */
.sidebar--expanded .sidebar__tooltip {
  display: none;
}

/* ─── Right panel ────────────────────────────────────────────────────────────── */
.panel {
  flex: 1;
  background: #fff;
  border-radius: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}

/* ─── Topbar ──────────────────────────────────────────────────────────────────── */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  border-bottom: 1px solid var(--g100);
  flex-shrink: 0;
  z-index: var(--z-topbar);
  background: #fff;
}

.topbar__left { display: flex; align-items: center; gap: 10px; }

.topbar__route {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--g600);
  margin: 0;
  letter-spacing: -0.01em;
}

.topbar__right { display: flex; align-items: center; gap: 8px; }

.topbar__avatar {
  width: 30px;
  height: 30px;
  background: var(--g900);
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}

.topbar__avatar-link {
  text-decoration: none;
  display: inline-block;
  transition: transform 0.15s ease, opacity 0.15s ease;
}

.topbar__avatar-link:hover {
  transform: scale(1.06);
  opacity: 0.9;
}

.topbar__avatar-link:active {
  transform: scale(0.95);
}

/* ─── Main slot area ─────────────────────────────────────────────────────────── */
.panel__main {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
</style>

