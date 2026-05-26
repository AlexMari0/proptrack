<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Invoice extends Model
{
    use HasFactory, HasUuids, Auditable;

    protected $fillable = [
        'contract_id',
        'tenant_id',
        'property_id',
        'invoice_number',
        'status',
        'amount',
        'billing_month',
        'due_date',
        'paid_at',
        'payment_gateway',
        'payment_token',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'due_date' => 'date',
        'paid_at'  => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isUnpaid(): bool  { return $this->status === 'unpaid'; }
    public function isPaid(): bool    { return $this->status === 'paid'; }
    public function isOverdue(): bool { return $this->status === 'overdue'; }
}
