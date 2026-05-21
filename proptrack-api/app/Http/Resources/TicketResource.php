<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'ticket_number' => $this->ticket_number,
            'status'        => $this->status,
            'priority'      => $this->priority,
            'category'      => $this->category,
            'title'         => $this->title,
            'description'   => $this->description,
            'property'      => [
                'id'   => $this->property->id,
                'name' => $this->property->name,
            ],
            'submitted_by'  => [
                'id'   => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
            ],
            'assigned_to'   => $this->assignedTo ? [
                'id'   => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null,
            'comments'      => TicketCommentResource::collection($this->whenLoaded('comments')),
            'created_at'    => $this->created_at->toIso8601String(),
            'updated_at'    => $this->updated_at->toIso8601String(),
        ];
    }
}
