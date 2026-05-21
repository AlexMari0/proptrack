export type TicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed';
export type TicketPriority = 'low' | 'medium' | 'high';
export type TicketCategory = 'maintenance' | 'billing' | 'other';

export interface TicketComment {
  id: string;
  content: string;
  user: {
    id: number;
    name: string;
    role: string;
  };
  created_at: string;
  updated_at: string;
}

export interface Ticket {
  id: string;
  ticket_number: string;
  status: TicketStatus;
  priority: TicketPriority;
  category: TicketCategory;
  title: string;
  description: string;
  property: {
    id: string;
    name: string;
  };
  submitted_by: {
    id: number;
    name: string;
  };
  assigned_to: {
    id: number;
    name: string;
  } | null;
  comments?: TicketComment[];
  created_at: string;
  updated_at: string;
}

export interface CreateTicketPayload {
  property_id: string;
  category: TicketCategory;
  priority: TicketPriority;
  title: string;
  description: string;
}

export interface UpdateTicketStatusPayload {
  status: TicketStatus;
  assigned_to_id?: number | null;
}

export interface TicketMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface TicketListResponse {
  data: Ticket[];
  meta: TicketMeta;
  message: string;
}

export interface TicketResponse {
  data: Ticket;
  message: string;
}

export interface TicketCommentResponse {
  data: TicketComment;
  message: string;
}

export interface TicketFilters {
  search: string;
  status: TicketStatus | '';
  priority: TicketPriority | '';
  category: TicketCategory | '';
  property_id: string;
  page: number;
  per_page: number;
}
