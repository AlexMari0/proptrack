import api from '@/plugins/axios'
import type { NotificationResponse } from '@/types/notification'

export const notificationService = {
  /**
   * Fetch authenticated user's notifications.
   */
  async getNotifications(page = 1, perPage = 15): Promise<NotificationResponse> {
    const response = await api.get<NotificationResponse>('/api/v1/notifications', {
      params: { page, per_page: perPage },
    })
    return response.data
  },

  /**
   * Mark a single notification as read.
   */
  async markAsRead(id: string): Promise<{ message: string }> {
    const response = await api.put<{ message: string }>(`/api/v1/notifications/${id}/read`)
    return response.data
  },

  /**
   * Mark all notifications as read.
   */
  async markAllAsRead(): Promise<{ message: string }> {
    const response = await api.put<{ message: string }>('/api/v1/notifications/read-all')
    return response.data
  },
}
