// ─── Payment types ─────────────────────────────────────────────────────────────

export interface CreateTransactionRequest {
  invoice_id: string
  gateway: 'midtrans'
}

export interface CreateTransactionResponse {
  data: {
    transaction_token: string
    redirect_url: string
    invoice_id: string
  }
  message: string
}

export interface PaymentStatusResponse {
  data: {
    invoice_id: string
    status: 'unpaid' | 'paid' | 'overdue' | 'cancelled'
    paid_at: string | null
    payment_gateway: string | null
  }
  message: string
}

// Midtrans Snap global — injected via CDN script tag
declare global {
  interface Window {
    snap: {
      pay: (
        token: string,
        options?: {
          onSuccess?: (result: MidtransResult) => void
          onPending?: (result: MidtransResult) => void
          onError?: (result: MidtransResult) => void
          onClose?: () => void
        }
      ) => void
    }
  }
}

export interface MidtransResult {
  order_id: string
  transaction_status: string
  payment_type?: string
}
