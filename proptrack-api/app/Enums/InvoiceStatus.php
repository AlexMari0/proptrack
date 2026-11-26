<?php

namespace App\Enums;

/**
 * The recorded status of an Invoice.
 *
 * "Overdue" is deliberately absent: it is derived from the due date
 * (see Invoice::effectiveStatus() and docs/adr/0001-overdue-is-derived.md).
 */
enum InvoiceStatus: string
{
    case Unpaid    = 'unpaid';
    case Paid      = 'paid';
    case Cancelled = 'cancelled';
}
