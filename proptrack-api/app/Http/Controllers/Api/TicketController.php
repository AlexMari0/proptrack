<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    /**
     * GET /api/v1/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::with(['property', 'submittedBy', 'assignedTo']);

        // Scope query based on role
        if ($user->hasRole('owner')) {
            $query->whereHas('property', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        } elseif ($user->hasRole('tenant')) {
            $query->where('submitted_by_id', $user->id);
        } // Admin/Agent can see all, so no extra scope applied

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $perPage = (int) $request->get('per_page', 15);
        $tickets = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => TicketResource::collection($tickets),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'per_page'     => $tickets->perPage(),
                'total'        => $tickets->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/tickets
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket($request->user(), $request->validated());

        return response()->json([
            'data'    => new TicketResource($ticket->load(['property', 'submittedBy', 'assignedTo'])),
            'message' => 'Ticket created successfully',
        ], 201);
    }

    /**
     * GET /api/v1/tickets/{ticket}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['property', 'submittedBy', 'assignedTo', 'comments.user.roles']);

        return response()->json([
            'data'    => new TicketResource($ticket),
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/tickets/{ticket}/status
     */
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('updateStatus', $ticket);

        $validated = $request->validated();
        $updatedTicket = $this->ticketService->updateStatus(
            $request->user(),
            $ticket,
            $validated['status'],
            $validated['assigned_to_id'] ?? null
        );

        return response()->json([
            'data'    => new TicketResource($updatedTicket),
            'message' => 'Ticket status updated successfully',
        ]);
    }

    /**
     * POST /api/v1/tickets/{ticket}/comments
     */
    public function storeComment(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('comment', $ticket);

        $comment = $this->ticketService->addComment(
            $request->user(),
            $ticket,
            $request->input('content')
        );

        return response()->json([
            'data'    => new TicketCommentResource($comment->load('user.roles')),
            'message' => 'Comment added successfully',
        ], 201);
    }
}
