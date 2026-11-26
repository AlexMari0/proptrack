<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use App\Traits\Auditable;

class Tenant extends Model
{
    use HasFactory, HasUuids, Notifiable, Auditable;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'id_card_number',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    /**
     * The login account this tenant profile belongs to, if the tenant has one.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant's current active contract (if any).
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('status', 'active')->with('property');
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    /**
     * Route notifications for the Fonnte channel.
     */
    public function routeNotificationForFonnte(): string
    {
        return $this->phone;
    }

    /**
     * Get the notifiable target (User model if registered, otherwise falls back to Tenant).
     */
    public function getNotifiable()
    {
        return $this->user ?? $this;
    }
}
