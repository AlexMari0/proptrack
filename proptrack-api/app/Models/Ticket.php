<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Ticket extends Model
{
    use HasFactory, HasUuids, Auditable;

    protected $fillable = [
        'ticket_number',
        'property_id',
        'submitted_by_id',
        'assigned_to_id',
        'category',
        'priority',
        'status',
        'title',
        'description',
    ];

    /**
     * Get the property associated with the ticket.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who submitted the ticket.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Get the comments for the ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Tickets the user may see. The one rule used by the policy, list
     * endpoint and broadcast channel: admins and agents see all, owners see
     * their own properties', tenants see the tickets they submitted.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'agent'])) {
            return $query;
        }

        if ($user->hasRole('owner')) {
            return $query->whereRelation('property', 'owner_id', $user->id);
        }

        if ($user->hasRole('tenant')) {
            return $query->where('submitted_by_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
