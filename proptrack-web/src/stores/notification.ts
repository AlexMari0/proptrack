import { defineStore } from 'pinia'
import type { Notification } from '@/types/notification'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as Notification[],
    unreadCount: 0,
    isLoading: false,
    error: null as string | null,
  }),

  actions: {
    setNotifications(notifications: Notification[], unreadCount: number) {
      this.notifications = notifications
      this.unreadCount = unreadCount
    },

    addNotification(notification: Notification) {
      const exists = this.notifications.some((n) => n.id === notification.id)
      if (!exists) {
        this.notifications.unshift(notification)
        if (notification.read_at === null) {
          this.unreadCount += 1
        }
      }
    },

    setUnreadCount(count: number) {
      this.unreadCount = count
    },

    decrementUnreadCount() {
      if (this.unreadCount > 0) {
        this.unreadCount -= 1
      }
    },

    markNotificationAsRead(id: string) {
      const notification = this.notifications.find((n) => n.id === id)
      if (notification && notification.read_at === null) {
        notification.read_at = new Date().toISOString()
        this.decrementUnreadCount()
      }
    },

    markAllNotificationsAsRead() {
      this.notifications.forEach((n) => {
        if (n.read_at === null) {
          n.read_at = new Date().toISOString()
        }
      })
      this.unreadCount = 0
    },

    setLoading(value: boolean) {
      this.isLoading = value
    },

    setError(message: string | null) {
      this.error = message
    },
  },
})
