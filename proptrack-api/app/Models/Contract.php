<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Contract extends Model
{
    use HasFactory, HasUuids, Auditable;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'deposit_amount',
        'billing_date',
        'status',
        'terminated_at',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'terminated_at'  => 'datetime',
        'monthly_rent'   => 'integer',
        'deposit_amount' => 'integer',
        'billing_date'   => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Contracts the user may see. The one rule used by the policy, list
     * endpoints, broadcast channels and reports:
     * admins see all, owners see their own properties', tenants see their own. Agents see none.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('owner')) {
            return $query->whereRelation('property', 'owner_id', $user->id);
        }

        if ($user->hasRole('tenant')) {
            return $query->whereRelation('tenant', 'user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
