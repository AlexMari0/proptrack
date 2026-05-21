export interface Notification {
  id: string;
  type: 'new_ticket' | 'ticket_status_changed' | 'invoice_created' | 'invoice_due' | 'payment_confirmed' | 'contract_expiring';
  title: string;
  message: string;
  data: {
    ticket_id?: string;
    invoice_id?: string;
    contract_id?: string;
  };
  read_at: string | null;
  created_at: string;
}

export interface NotificationResponse {
  data: Notification[];
  meta: {
    unread_count: number;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message: string;
}
