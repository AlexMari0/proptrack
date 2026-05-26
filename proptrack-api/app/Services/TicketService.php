<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    /**
     * Create a new ticket.
     */
    public function createTicket(User $user, array $data): Ticket
    {
        // If the user has a tenant role, ensure they have an active contract for the selected property
        if ($user->hasRole('tenant')) {
            $tenant = Tenant::where('email', $user->email)->first();
            if (!$tenant) {
                throw ValidationException::withMessages([
                    'property_id' => 'Tenant profile not found for this user.',
                ]);
            }

            $hasActiveContract = Contract::where('tenant_id', $tenant->id)
                ->where('property_id', $data['property_id'])
                ->where('status', 'active')
                ->exists();

            if (!$hasActiveContract) {
                throw ValidationException::withMessages([
                    'property_id' => 'You do not have an active contract for this property.',
                ]);
            }
        }

        $ticket = DB::transaction(function () use ($user, $data) {
            $year = now()->year;

            // Generate unique sequential ticket number TKT-YYYY-XXXX
            // Using lockForUpdate to ensure thread-safety
            $lastTicket = Ticket::whereYear('created_at', $year)
                ->lockForUpdate()
                ->orderBy('ticket_number', 'desc')
                ->first();

            $nextSequence = 1;
            if ($lastTicket && preg_match('/TKT-\d{4}-(\d+)/', $lastTicket->ticket_number, $matches)) {
                $nextSequence = (int)$matches[1] + 1;
            }

            $ticketNumber = sprintf('TKT-%d-%04d', $year, $nextSequence);

            return Ticket::create([
                'ticket_number'   => $ticketNumber,
                'property_id'     => $data['property_id'],
                'submitted_by_id' => $user->id,
                'category'        => $data['category'],
                'priority'        => $data['priority'],
                'status'          => 'open',
                'title'           => $data['title'],
                'description'     => $data['description'],
            ]);
        });

        // Notify agents and admins
        $recipients = \App\Models\User::role(['agent', 'admin'])->get();
        foreach ($recipients as $recipient) {
            $recipient->notify(new \App\Notifications\NewTicketNotification($ticket));
        }

        return $ticket;
    }

    /**
     * Update ticket status and assignment.
     */
    public function updateStatus(User $user, Ticket $ticket, string $status, ?int $assignedToId = null): Ticket
    {
        $oldStatus = $ticket->status;
        $updateData = ['status' => $status];

        if ($assignedToId !== null) {
            $updateData['assigned_to_id'] = $assignedToId;
        }

        $ticket->update($updateData);

        // If status changed, notify tenant
        if ($oldStatus !== $status) {
            $tenantUser = $ticket->submittedBy;
            if ($tenantUser) {
                $tenantUser->notify(new \App\Notifications\TicketStatusChangedNotification($ticket));
            }
            // Broadcast ticket status updated event
            event(new \App\Events\TicketStatusUpdated($ticket));
        }

        return $ticket->fresh(['property', 'submittedBy', 'assignedTo', 'comments']);
    }

    /**
     * Add a comment to the ticket.
     */
    public function addComment(User $user, Ticket $ticket, string $content): TicketComment
    {
        return TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'content'   => $content,
        ]);
    }
}
