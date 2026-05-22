<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import NavbarNotificationBell from '@/components/layout/NavbarNotificationBell.vue'

const authStore = useAuthStore()
const { logout, loading: authLoading } = useAuth()

const isOwnerOrAdmin = () =>
  ['owner', 'admin'].some(role => authStore.user?.roles?.includes(role))
const isAgent = () => authStore.user?.roles?.includes('agent')

const isExpanded = ref(localStorage.getItem('sidebar_expanded') === 'true')
const toggleSidebar = () => {
  isExpanded.value = !isExpanded.value
  localStorage.setItem('sidebar_expanded', isExpanded.value ? 'true' : 'false')
}

const triggerBellClick = (e: MouseEvent) => {
  const container = (e.target as HTMLElement).closest('.sidebar__bell')
  const button = container?.querySelector('button')
  button?.click()
}
</script>

<template>
  <div class="shell">
    <!-- ═══ Icon/Expanded sidebar ═════════════════════════════════════════════ -->
    <aside class="sidebar" :class="{ 'sidebar--expanded': isExpanded }" aria-label="Site navigation">
      <div class="sidebar__logo">
        <RouterLink to="/dashboard" class="logo-link" aria-label="PropTrack home">
          <span class="logo-mark">P</span>
          <span class="logo-text">PropTrack</span>
        </RouterLink>
      </div>

      <nav class="sidebar__nav" aria-label="Main">
        <RouterLink to="/dashboard" class="sidebar__link" title="Dashboard" aria-label="Dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span class="sidebar__label">Dashboard</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin() || isAgent()" to="/properties" class="sidebar__link" title="Properties" aria-label="Properties">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M8 10h.01M8 14h.01M16 10h.01M16 14h.01"/></svg>
          <span class="sidebar__label">Properties</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin() || isAgent()" to="/tenants" class="sidebar__link" title="Tenants" aria-label="Tenants">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span class="sidebar__label">Tenants</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/contracts" class="sidebar__link" title="Contracts" aria-label="Contracts">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
          <span class="sidebar__label">Contracts</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/invoices" class="sidebar__link" title="Invoices" aria-label="Invoices">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M16 8H8"/><path d="M16 12H8"/><path d="M12 16H8"/></svg>
          <span class="sidebar__label">Invoices</span>
        </RouterLink>
        <RouterLink to="/tickets" class="sidebar__link" title="Tickets" aria-label="Support tickets">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
          <span class="sidebar__label">Tickets</span>
        </RouterLink>
        <RouterLink v-if="isOwnerOrAdmin()" to="/reports/financial" class="sidebar__link" title="Reports" aria-label="Financial reports">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>
          <span class="sidebar__label">Reports</span>
        </RouterLink>
      </nav>

      <div class="sidebar__bottom">
        <div class="sidebar__bell">
          <NavbarNotificationBell />
          <span class="sidebar__label" @click="triggerBellClick">Notifications</span>
        </div>
        <button class="sidebar__link sidebar__link--logout" @click="logout" :disabled="authLoading" title="Sign out" aria-label="Sign out">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          <span class="sidebar__label">Sign out</span>
        </button>
        <button class="sidebar__link sidebar__toggle" @click="toggleSidebar" :title="isExpanded ? 'Collapse' : 'Expand'" :aria-label="isExpanded ? 'Collapse sidebar' : 'Expand sidebar'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" :class="{ 'rotate-180': isExpanded }">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
          <span class="sidebar__label">Collapse</span>
        </button>
      </div>
    </aside>

    <!-- ═══ Right panel ═══════════════════════════════════════════════════════ -->
    <div class="panel">
      <!-- Top bar -->
      <header class="topbar" role="banner">
        <div class="topbar__left">
          <!-- Breadcrumb-style page label -->
          <p class="topbar__route">{{ authStore.user?.name }}</p>
        </div>
        <div class="topbar__right">
          <div class="topbar__avatar" aria-hidden="true">
            {{ authStore.user?.name?.[0]?.toUpperCase() || 'U' }}
          </div>
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
  transition: width 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded {
  width: 220px;
  align-items: stretch;
}

.sidebar__logo {
  margin-bottom: 18px;
  width: 100%;
  display: flex;
  justify-content: center;
}

.sidebar--expanded .sidebar__logo {
  justify-content: flex-start;
  padding-left: 2px;
}

.logo-link {
  display: flex;
  align-items: center;
  text-decoration: none;
  gap: 12px;
}

.logo-mark {
  display: flex;
  width: 36px;
  height: 36px;
  background: var(--g900);
  color: #fff;
  border-radius: 10px;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1.05rem;
  letter-spacing: -0.03em;
  transition: transform 0.15s;
  flex-shrink: 0;
}
.logo-mark:hover { transform: scale(1.06); }

.logo-text {
  font-weight: 800;
  font-size: 1.15rem;
  color: var(--g900);
  letter-spacing: -0.02em;
  opacity: 0;
  max-width: 0;
  overflow: hidden;
  white-space: nowrap;
  transition: opacity 0.1s ease, max-width 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar--expanded .logo-text {
  opacity: 1;
  max-width: 140px;
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
  transition: background 0.15s, color 0.15s, transform 0.12s, width 0.25s cubic-bezier(0.16, 1, 0.3, 1), padding 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
  border: none;
  background: transparent;
  text-decoration: none;
  box-sizing: border-box;
  overflow: hidden;
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

.sidebar__link.router-link-active { background: var(--g900); color: #fff; }

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
  transition: opacity 0.1s ease, max-width 0.25s cubic-bezier(0.16, 1, 0.3, 1), margin-left 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  margin-left: 0;
  color: inherit;
}

.sidebar--expanded .sidebar__label {
  opacity: 1;
  max-width: 140px;
  margin-left: 10px;
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

/* Bell wrapper styling */
.sidebar__bell {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 40px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s, padding 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}

.sidebar--expanded .sidebar__bell {
  justify-content: flex-start;
  padding: 0 8px;
}

.sidebar__bell:hover {
  background: var(--g50);
}

.sidebar__bell :deep(.relative) {
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar__bell .sidebar__label {
  color: var(--g500);
  cursor: pointer;
}

.sidebar__bell:hover .sidebar__label {
  color: var(--g700);
}

/* Rotate chevron for toggle button */
.sidebar__toggle svg {
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.sidebar__toggle svg.rotate-180 {
  transform: rotate(180deg);
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

/* ─── Main slot area ─────────────────────────────────────────────────────────── */
.panel__main {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
</style>

