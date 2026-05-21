<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $slugMap = [
            \App\Notifications\NewTicketNotification::class => 'new_ticket',
            \App\Notifications\TicketStatusChangedNotification::class => 'ticket_status_changed',
            \App\Notifications\InvoiceCreatedNotification::class => 'invoice_created',
            \App\Notifications\InvoiceDueNotification::class => 'invoice_due',
            \App\Notifications\PaymentConfirmedNotification::class => 'payment_confirmed',
            \App\Notifications\ContractExpiringNotification::class => 'contract_expiring',
        ];

        $type = $slugMap[$this->type] ?? $this->type;

        return [
            'id' => $this->id,
            'type' => $type,
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'data' => collect($this->data)->except(['title', 'message'])->toArray(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
