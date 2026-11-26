<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceIssued;
use App\Events\InvoicePaid;
use App\Exceptions\InvalidInvoiceTransition;
use App\Numbering\DocumentNumbers;
use App\Traits\Auditable;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * An Invoice and its lifecycle.
 *
 * Every status change goes through this model (issueFor, markPaid), so each
 * one is audited and announced by exactly one domain event. The recorded
 * status is one of InvoiceStatus; "overdue" is derived from the due date in
 * the billing timezone (docs/adr/0001-overdue-is-derived.md).
 */
class Invoice extends Model
{
    use HasFactory, HasUuids, Auditable;

    /** Effective statuses, as exposed by the API and accepted by withEffectiveStatus(). */
    public const EFFECTIVE_STATUSES = ['unpaid', 'overdue', 'paid', 'cancelled'];

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

    // ─── Billing time ─────────────────────────────────────────────────────────

    /**
     * Today's date in the billing timezone. All due/overdue rules use this.
     */
    public static function billingToday(): CarbonImmutable
    {
        return CarbonImmutable::now(config('billing.timezone'))->startOfDay();
    }

    // ─── Transitions ──────────────────────────────────────────────────────────

    /**
     * Issue the Invoice for a Contract's billing month ("YYYY-MM").
     *
     * Returns null when the Contract already has an Invoice for that month.
     * Dispatches InvoiceIssued.
     */
    public static function issueFor(Contract $contract, string $billingMonth): ?self
    {
        $invoice = DB::transaction(function () use ($contract, $billingMonth) {
            $exists = static::where('contract_id', $contract->id)
                ->where('billing_month', $billingMonth)
                ->exists();

            if ($exists) {
                return null;
            }

            return static::create([
                'contract_id'    => $contract->id,
                'tenant_id'      => $contract->tenant_id,
                'property_id'    => $contract->property_id,
                'invoice_number' => app(DocumentNumbers::class)->next(DocumentNumbers::INVOICE),
                'status'         => InvoiceStatus::Unpaid->value,
                'amount'         => $contract->monthly_rent,
                'billing_month'  => $billingMonth,
                'due_date'       => static::dueDateFor($billingMonth, $contract->billing_date),
            ]);
        });

        if ($invoice) {
            InvoiceIssued::dispatch($invoice);
        }

        return $invoice;
    }

    /**
     * Record settlement of this Invoice by a payment gateway.
     *
     * Idempotent: returns false (and dispatches nothing) when already paid,
     * so gateway webhook retries are safe. Dispatches InvoicePaid otherwise.
     *
     * @throws InvalidInvoiceTransition when the Invoice cannot be paid (cancelled).
     */
    public function markPaid(string $gateway): bool
    {
        $paid = DB::transaction(function () use ($gateway) {
            $current = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($current->isPaid()) {
                return false;
            }

            if (! $current->isOutstanding()) {
                throw InvalidInvoiceTransition::cannotPay($current);
            }

            $current->update([
                'status'          => InvoiceStatus::Paid->value,
                'paid_at'         => now(),
                'payment_gateway' => $gateway,
            ]);

            $this->setRawAttributes($current->getAttributes(), true);

            return true;
        });

        if ($paid) {
            InvoicePaid::dispatch($this);
        }

        return $paid;
    }

    // ─── State ────────────────────────────────────────────────────────────────

    /** Issued and not yet paid (includes overdue). */
    public function isOutstanding(): bool
    {
        return $this->status === InvoiceStatus::Unpaid->value;
    }

    /** Outstanding and past its due date in the billing timezone. */
    public function isOverdue(): bool
    {
        return $this->isOutstanding()
            && $this->due_date !== null
            && $this->due_date->toDateString() < static::billingToday()->toDateString();
    }

    /** Outstanding and not yet overdue. */
    public function isUnpaid(): bool
    {
        return $this->isOutstanding() && ! $this->isOverdue();
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid->value;
    }

    /** Whether a Tenant can pay this Invoice right now. */
    public function isPayable(): bool
    {
        return $this->isOutstanding();
    }

    /** One of EFFECTIVE_STATUSES: the recorded status, with overdue derived. */
    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Unpaid->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()
            ->whereDate('due_date', '<', static::billingToday()->toDateString());
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->outstanding()
            ->whereDate('due_date', '>=', static::billingToday()->toDateString());
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Paid->value);
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', InvoiceStatus::Cancelled->value);
    }

    /**
     * Filter by effective status (one of EFFECTIVE_STATUSES). Unknown values match nothing.
     */
    public function scopeWithEffectiveStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'unpaid'    => $query->unpaid(),
            'overdue'   => $query->overdue(),
            'paid'      => $query->paid(),
            'cancelled' => $query->where('status', InvoiceStatus::Cancelled->value),
            default     => $query->whereRaw('1 = 0'),
        };
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Due date: the Contract's billing day within the billing month,
     * clamped to the month's last day.
     */
    private static function dueDateFor(string $billingMonth, int $billingDay): Carbon
    {
        // Explicit day 01: 'Y-m' alone fills in today's day and overflows on the 29th–31st.
        $date = Carbon::createFromFormat('Y-m-d', "{$billingMonth}-01")->startOfDay();

        return $date->setDay(min($billingDay, $date->daysInMonth));
    }

    // ─── Visibility ───────────────────────────────────────────────────────────

    /**
     * Invoices the user may see. The one rule used by the policy, list
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
