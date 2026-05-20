<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory, HasUuids;

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
}
