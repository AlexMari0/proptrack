<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useNotification } from '@/composables/useNotification'
import { useAuthStore } from '@/stores/auth'
import { useEcho } from '@/composables/useEcho'
import type { Notification } from '@/types/notification'

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

const isOpen = ref(false)
const bellContainer = ref<HTMLElement | null>(null)
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

function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    // Refresh notifications when dropdown is opened
    fetchNotifications()
  }
}

function closeDropdown(e: MouseEvent) {
  if (bellContainer.value && !bellContainer.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

watch(() => authStore.user?.id, (newId) => {
  if (newId) {
    subscribeToNotifications(String(newId))
  } else {
    unsubscribeFromNotifications()
  }
}, { immediate: true })

onMounted(() => {
  fetchNotifications()
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown)
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
  isOpen.value = false
  await handleNotificationClick(notification)
}
</script>

<template>
  <div ref="bellContainer" class="relative">
    <!-- Notification Bell Button -->
    <button
      @click="toggleDropdown"
      class="relative p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/50 text-slate-300 hover:text-white transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0 focus:outline-none flex items-center justify-center cursor-pointer"
      aria-label="Notifications"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="size-5"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
        />
      </svg>

      <!-- Glow Badge -->
      <span
        v-if="unreadCount > 0"
        class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white ring-2 ring-[#0b0f19] animate-pulse"
      >
        {{ unreadCount }}
      </span>
    </button>

    <!-- Glassmorphism Dropdown Menu -->
    <Transition name="fade-slide">
      <div
        v-if="isOpen"
        class="absolute right-0 mt-3 w-80 sm:w-96 rounded-2xl border border-slate-800/90 bg-slate-900/90 backdrop-blur-xl shadow-2xl z-50 overflow-hidden"
      >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-800/60 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-white">Notifications</span>
            <span
              v-if="unreadCount > 0"
              class="px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-400 font-semibold text-[10px] border border-rose-500/20"
            >
              {{ unreadCount }} new
            </span>
          </div>
          <button
            v-if="unreadCount > 0"
            @click="onMarkAllAsRead"
            class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1 cursor-pointer"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="2"
              stroke="currentColor"
              class="size-3.5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Mark all read
          </button>
        </div>

        <!-- Notification List -->
        <div class="max-h-[360px] overflow-y-auto divide-y divide-slate-800/40 custom-scrollbar">
          <div v-if="isLoading && notifications.length === 0" class="p-8 flex justify-center">
            <div class="w-8 h-8 rounded-full border-2 border-slate-800 border-t-indigo-500 animate-spin"></div>
          </div>

          <template v-else-if="notifications.length > 0">
            <div
              v-for="item in notifications"
              :key="item.id"
              @click="onNotificationItemClick(item)"
              class="p-4 flex gap-3 hover:bg-slate-800/40 transition-all duration-200 cursor-pointer relative group"
              :class="item.read_at ? 'opacity-70' : 'bg-indigo-950/5'"
            >
              <!-- Unread Indicator Dot -->
              <span
                v-if="!item.read_at"
                class="absolute left-1.5 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-indigo-500"
              ></span>

              <!-- Dynamic Type Scoped Icon -->
              <div class="flex-shrink-0">
                <!-- Ticket Icons -->
                <div
                  v-if="item.type === 'new_ticket' || item.type === 'ticket_status_changed'"
                  class="w-9 h-9 rounded-xl flex items-center justify-center border"
                  :class="
                    item.type === 'new_ticket'
                      ? 'bg-amber-500/10 border-amber-500/20 text-amber-400'
                      : 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400'
                  "
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"
                    />
                  </svg>
                </div>

                <!-- Invoice Created / Invoice Due Icons -->
                <div
                  v-else-if="item.type === 'invoice_created' || item.type === 'invoice_due'"
                  class="w-9 h-9 rounded-xl flex items-center justify-center border"
                  :class="
                    item.type === 'invoice_due'
                      ? 'bg-rose-500/10 border-rose-500/20 text-rose-400'
                      : 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400'
                  "
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                    />
                  </svg>
                </div>

                <!-- Payment Confirmed Icons -->
                <div
                  v-else-if="item.type === 'payment_confirmed'"
                  class="w-9 h-9 rounded-xl flex items-center justify-center border bg-emerald-500/10 border-emerald-500/20 text-emerald-400"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"
                    />
                  </svg>
                </div>

                <!-- Contract Expiring Icons -->
                <div
                  v-else
                  class="w-9 h-9 rounded-xl flex items-center justify-center border bg-purple-500/10 border-purple-500/20 text-purple-400"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-5"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                    />
                  </svg>
                </div>
              </div>

              <!-- Message details -->
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-white truncate mb-0.5 group-hover:text-indigo-400 transition-colors">
                  {{ item.title }}
                </p>
                <p class="text-[11px] text-slate-400 leading-relaxed mb-1 line-clamp-2">
                  {{ item.message }}
                </p>
                <span class="text-[10px] text-slate-500 font-medium">
                  {{ formatTime(item.created_at) }}
                </span>
              </div>
            </div>
          </template>

          <!-- Empty State -->
          <div v-else class="py-12 px-6 flex flex-col items-center justify-center text-center">
            <div class="w-12 h-12 rounded-full bg-slate-800/50 border border-slate-700/30 flex items-center justify-center text-slate-500 mb-3 shadow-inner">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-6"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                />
              </svg>
            </div>
            <h4 class="text-xs font-bold text-slate-300 mb-1">All Caught Up!</h4>
            <p class="text-[10px] text-slate-500 max-w-[200px]">
              You don't have any notifications right now.
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Scrollbar Customization */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
