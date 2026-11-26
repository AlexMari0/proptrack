export interface AuditUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
}

export interface AuditLog {
  id: string;
  user: AuditUser | null;
  /** Non-user actor, e.g. "gateway:midtrans", "system:invoices:generate". Null when a user acted. */
  source: string | null;
  action: 'create' | 'update' | 'delete';
  model_type: string;
  model_id: string;
  old_values: Record<string, any> | null;
  new_values: Record<string, any> | null;
  ip_address: string | null;
  created_at: string;
}
