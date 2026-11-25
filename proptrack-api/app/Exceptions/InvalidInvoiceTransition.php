<?php

namespace App\Exceptions;

use App\Models\Invoice;
use DomainException;

class InvalidInvoiceTransition extends DomainException
{
    public static function cannotPay(Invoice $invoice): self
    {
        return new self("Invoice {$invoice->invoice_number} is {$invoice->status} and cannot be paid.");
    }
}
