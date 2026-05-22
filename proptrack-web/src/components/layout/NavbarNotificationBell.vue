<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'
import { useNotification } from '@/composables/useNotification'
import { useAuthStore } from '@/stores/auth'
import { useEcho } from '@/composables/useEcho'
import type { Notification } from '@/types/notification'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const {
  notifications,
  unreadCount,
  isLoading,
  fetchNotifications,
  markAllAsRead,
  handleNotificationClick,
  addNotification
} = useNotification()

const authStore = useAuthStore()
const { getEcho } = useEcho()

let activeChannel: string | null = null

function subscribeToNotifications(userId: string) {
  const echo = getEcho()
  if (echo) {
    if (activeChannel) {
      echo.leave(`user.${activeChannel}`)
    }
    activeChannel = userId
    echo.private(`user.${userId}`)
      .listen('.NotificationSent', (e: any) => {
        if (e.notification) {
          addNotification(e.notification)
        }
      })
  }
}

function unsubscribeFromNotifications() {
  const echo = getEcho()
  if (echo && activeChannel) {
    echo.leave(`user.${activeChannel}`)
    activeChannel = null
  }
}

function closeDrawer() {
  emit('close')
}

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchNotifications()
  }
})

watch(() => authStore.user?.id, (newId) => {
  if (newId) {
    subscribeToNotifications(String(newId))
  } else {
    unsubscribeFromNotifications()
  }
}, { immediate: true })

onMounted(() => {
  fetchNotifications()
})

onUnmounted(() => {
  unsubscribeFromNotifications()
})

function formatTime(dateStr: string) {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays === 1) return 'Yesterday'
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function onMarkAllAsRead() {
  await markAllAsRead()
}

async function onNotificationItemClick(notification: Notification) {
  emit('close')
  await handleNotificationClick(notification)
}
</script>

<template>
  <!-- Slide-in Drawer overlay (teleported to body for zero z-index/overflow issues) -->
  <Teleport to="body">
    <div v-if="isOpen" class="notif-backdrop" @click="closeDrawer" aria-hidden="true"></div>
    <Transition name="slide-in">
      <div v-if="isOpen" class="notif-drawer" role="dialog" aria-modal="true" aria-label="Notifications panel">
        <!-- Header -->
        <div class="notif-header">
          <div class="notif-header__top">
            <div class="notif-header__title-row">
              <h2 class="notif-title">Notifications</h2>
              <span v-if="unreadCount > 0" class="notif-badge-pill">{{ unreadCount }} new</span>
            </div>
            <button @click="closeDrawer" class="btn-close" aria-label="Close notifications panel">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div v-if="unreadCount > 0" class="notif-header__bottom-row">
            <button
              @click="onMarkAllAsRead"
              class="btn-mark-all"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Mark all read
            </button>
          </div>
        </div>

        <!-- Notification List -->
        <div class="notif-list custom-scrollbar">
          <div v-if="isLoading && notifications.length === 0" class="notif-loading">
            <div class="spinner"></div>
          </div>

          <template v-else-if="notifications.length > 0">
            <div
              v-for="item in notifications"
              :key="item.id"
              @click="onNotificationItemClick(item)"
              class="notif-item"
              :class="{ 'notif-item--unread': !item.read_at }"
            >
              <!-- Unread dot -->
              <span v-if="!item.read_at" class="notif-unread-dot"></span>

              <!-- Type-scoped Icon container -->
              <div class="notif-item__icon-wrapper">
                <div class="notif-icon" :class="`notif-icon--${item.type}`">
                  <!-- Ticket icons -->
                  <svg v-if="item.type === 'new_ticket' || item.type === 'ticket_status_changed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                    <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                  </svg>
                  <!-- Invoice icons -->
                  <svg v-else-if="item.type === 'invoice_created' || item.type === 'invoice_due'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
                    <path d="M16 8H8"/><path d="M16 12H8"/>
                  </svg>
                  <!-- Payment icon -->
                  <svg v-else-if="item.type === 'payment_confirmed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                  </svg>
                  <!-- Default/Lease icon -->
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                  </svg>
                </div>
              </div>

              <!-- Message Details -->
              <div class="notif-item__details">
                <p class="notif-item__title">{{ item.title }}</p>
                <p class="notif-item__desc">{{ item.message }}</p>
                <span class="notif-item__time">{{ formatTime(item.created_at) }}</span>
              </div>
            </div>
          </template>

          <!-- Empty State -->
          <div v-else class="notif-empty">
            <div class="notif-empty__icon-circle">
              <!-- Elegant Bell Outline -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </div>
            <h3 class="notif-empty__title">All caught up!</h3>
            <p class="notif-empty__text">You don't have any notifications at the moment. We'll alert you here when new activity happens.</p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* ─── Slide-in Drawer ───────────────────────────────────────────────────────── */
.notif-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(26, 23, 18, 0.25);
  backdrop-filter: blur(4px);
  z-index: 1000; /* Must overlay topbar/sidebar */
}

.notif-drawer {
  position: fixed;
  top: 0;
  left: 0;
  width: 380px;
  height: 100dvh;
  background: #fff;
  box-shadow: 12px 0 40px rgba(26, 23, 18, 0.12);
  z-index: 1001;
  display: flex;
  flex-direction: column;
  font-family: 'Outfit', var(--font-sans);
  border-right: 1px solid var(--g100);
}

@media (max-width: 480px) {
  .notif-drawer {
    width: 100%;
  }
}

.notif-header {
  padding: 24px;
  border-bottom: 1px solid var(--g100);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notif-header__title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.notif-title {
  font-size: 1.2rem;
  font-weight: 850;
  color: var(--g900);
  margin: 0;
  letter-spacing: -0.02em;
}

.notif-badge-pill {
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  font-size: 0.7rem;
  font-weight: 700;
}

.notif-header__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.notif-header__bottom-row {
  display: flex;
  align-items: center;
}

.btn-mark-all {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: none;
  padding: 0;
  color: var(--amber);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.15s;
}

.btn-mark-all:hover {
  color: #92640a;
}

.btn-mark-all svg {
  width: 14px;
  height: 14px;
}

.btn-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--g50);
  border: none;
  color: var(--g500);
  cursor: pointer;
  transition: all 0.15s;
}

.btn-close:hover {
  background: var(--g100);
  color: var(--g900);
}

.btn-close svg {
  width: 14px;
  height: 14px;
}

/* ─── Notification List ─────────────────────────────────────────────────────── */
.notif-list {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.notif-loading {
  padding: 40px;
  display: flex;
  justify-content: center;
}

.spinner {
  width: 24px;
  height: 24px;
  border: 2.5px solid var(--g100);
  border-top-color: var(--g900);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.notif-item {
  padding: 16px 24px;
  display: flex;
  gap: 14px;
  border-bottom: 1px solid var(--g50);
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
  background: #fff;
  opacity: 0.75;
}

.notif-item:hover {
  background: var(--g50);
  opacity: 1;
}

.notif-item--unread {
  background: rgba(224, 156, 26, 0.03); /* subtle amber background tint for unread */
  opacity: 1;
}

.notif-unread-dot {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--amber);
}

.notif-item__icon-wrapper {
  flex-shrink: 0;
}

.notif-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--g100);
  background: var(--g50);
  color: var(--g600);
}

.notif-icon svg {
  width: 16px;
  height: 16px;
}

/* Color palettes based on event category types */
.notif-icon--new_ticket {
  background: rgba(224, 156, 26, 0.08);
  border-color: rgba(224, 156, 26, 0.18);
  color: var(--amber);
}

.notif-icon--ticket_status_changed {
  background: rgba(99, 102, 241, 0.08);
  border-color: rgba(99, 102, 241, 0.18);
  color: #6366f1;
}

.notif-icon--invoice_created,
.notif-icon--invoice_due {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.18);
  color: #dc2626;
}

.notif-icon--payment_confirmed {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.18);
  color: #15803d;
}

.notif-item__details {
  flex: 1;
  min-width: 0;
}

.notif-item__title {
  font-size: 0.8125rem;
  font-weight: 750;
  color: var(--g900);
  margin: 0 0 4px 0;
  letter-spacing: -0.015em;
  line-height: 1.25;
}

.notif-item__desc {
  font-size: 0.75rem;
  color: var(--g500);
  margin: 0 0 6px 0;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-item__time {
  font-size: 0.6875rem;
  color: var(--g400);
  font-weight: 500;
}

/* ─── Empty State ───────────────────────────────────────────────────────────── */
.notif-empty {
  padding: 80px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  justify-content: center;
  flex: 1;
}

.notif-empty__icon-circle {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--g50);
  border: 1px solid var(--g100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--g300);
  margin-bottom: 16px;
}

.notif-empty__icon-circle svg {
  width: 22px;
  height: 22px;
}

.notif-empty__title {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--g900);
  margin: 0 0 6px 0;
  letter-spacing: -0.015em;
}

.notif-empty__text {
  font-size: 0.78rem;
  color: var(--g400);
  margin: 0;
  line-height: 1.5;
  max-width: 240px;
}

/* ─── Animations ────────────────────────────────────────────────────────────── */
.slide-in-enter-active,
.slide-in-leave-active {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-in-enter-from,
.slide-in-leave-to {
  transform: translateX(-100%);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: var(--g100);
  border-radius: 999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: var(--g200);
}
</style>
