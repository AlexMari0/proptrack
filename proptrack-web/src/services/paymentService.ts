import api from '@/plugins/axios'
import type {
  CreateTransactionRequest,
  CreateTransactionResponse,
  PaymentStatusResponse,
} from '@/types/payment'

export const paymentService = {
  /**
   * Create a Midtrans Snap transaction for an invoice.
   * Returns the snap token and redirect URL.
   * Secret key is NEVER exposed — it lives on the backend only.
   */
  async createTransaction(payload: CreateTransactionRequest): Promise<CreateTransactionResponse> {
    const response = await api.post<CreateTransactionResponse>(
      '/api/v1/payments/create-transaction',
      payload,
    )
    return response.data
  },

  /**
   * Poll the payment status of an invoice.
   * Called every 3 seconds from PaymentPage while awaiting confirmation.
   */
  async getStatus(invoiceId: string): Promise<PaymentStatusResponse> {
    const response = await api.get<PaymentStatusResponse>(
      `/api/v1/payments/${invoiceId}/status`,
    )
    return response.data
  },

  /**
   * Dynamically load the Midtrans Snap.js script if not already loaded.
   * Snap URL comes from VITE_MIDTRANS_SNAP_URL (sandbox or production).
   */
  loadSnapScript(): Promise<void> {
    return new Promise((resolve, reject) => {
      if (window.snap) {
        resolve()
        return
      }

      const snapUrl = import.meta.env.VITE_MIDTRANS_SNAP_URL as string
      if (!snapUrl) {
        reject(new Error('VITE_MIDTRANS_SNAP_URL is not set'))
        return
      }

      const script = document.createElement('script')
      script.src = snapUrl
      script.setAttribute('data-client-key', import.meta.env.VITE_MIDTRANS_CLIENT_KEY as string)
      script.onload = () => resolve()
      script.onerror = () => reject(new Error('Failed to load Midtrans Snap script'))
      document.head.appendChild(script)
    })
  },
}
