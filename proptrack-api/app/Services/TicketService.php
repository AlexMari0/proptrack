<?php

namespace App\Services;

use App\Events\TicketOpened;
use App\Numbering\DocumentNumbers;
use App\Events\TicketStatusChanged;
use App\Models\Contract;
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
            $tenant = $user->tenantProfile;
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
            $ticketNumber = app(DocumentNumbers::class)->next(DocumentNumbers::TICKET);

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

        TicketOpened::dispatch($ticket);

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

        if ($oldStatus !== $status) {
            TicketStatusChanged::dispatch($ticket);
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
