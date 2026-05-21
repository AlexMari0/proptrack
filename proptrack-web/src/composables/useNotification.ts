import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { notificationService } from '@/services/notificationService'
import { useNotificationStore } from '@/stores/notification'
import type { Notification } from '@/types/notification'

export function useNotification() {
  const store = useNotificationStore()
  const router = useRouter()

  // ─── Exposed State ─────────────────────────────────────────────────────────
  const notifications = computed(() => store.notifications)
  const unreadCount = computed(() => store.unreadCount)
  const isLoading = computed(() => store.isLoading)
  const error = computed(() => store.error)

  // ─── Actions ───────────────────────────────────────────────────────────────

  /**
   * Fetch authenticated user's notifications list.
   */
  async function fetchNotifications(page = 1, perPage = 15): Promise<void> {
    store.setLoading(true)
    store.setError(null)
    try {
      const response = await notificationService.getNotifications(page, perPage)
      store.setNotifications(response.data, response.meta.unread_count)
    } catch {
      store.setError('Failed to fetch notifications.')
    } finally {
      store.setLoading(false)
    }
  }

  /**
   * Mark a specific notification as read.
   */
  async function markAsRead(id: string): Promise<boolean> {
    try {
      await notificationService.markAsRead(id)
      store.markNotificationAsRead(id)
      return true
    } catch {
      return false
    }
  }

  /**
   * Mark all unread notifications as read.
   */
  async function markAllAsRead(): Promise<boolean> {
    try {
      await notificationService.markAllAsRead()
      store.markAllNotificationsAsRead()
      return true
    } catch {
      return false
    }
  }

  /**
   * Click handler for a notification item.
   * Marks the notification as read and navigates to the relevant detail page.
   */
  async function handleNotificationClick(notification: Notification): Promise<void> {
    if (notification.read_at === null) {
      await markAsRead(notification.id)
    }

    const { type, data } = notification

    if (type === 'new_ticket' || type === 'ticket_status_changed') {
      if (data.ticket_id) {
        router.push({ name: 'ticket-detail', params: { id: data.ticket_id } })
      }
    } else if (type === 'invoice_created' || type === 'invoice_due' || type === 'payment_confirmed') {
      if (data.invoice_id) {
        router.push({ name: 'invoice-detail', params: { id: data.invoice_id } })
      }
    } else if (type === 'contract_expiring') {
      if (data.contract_id) {
        router.push({ name: 'contract-detail', params: { id: data.contract_id } })
      }
    }
  }

  function addNotification(notification: Notification): void {
    store.addNotification(notification)
  }

  return {
    // State
    notifications,
    unreadCount,
    isLoading,
    error,
    // Actions
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    handleNotificationClick,
    addNotification,
  }
}
