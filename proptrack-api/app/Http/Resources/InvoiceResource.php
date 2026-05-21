<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'invoice_number'  => $this->invoice_number,
            'status'          => $this->status,
            'amount'          => $this->amount,
            'billing_month'   => $this->billing_month,
            'due_date'        => $this->due_date?->toDateString(),
            'paid_at'         => $this->paid_at?->toIso8601String(),
            'payment_gateway' => $this->payment_gateway,
            'contract'        => [
                'id' => $this->contract?->id,
            ],
            'tenant'          => [
                'id'   => $this->tenant?->id,
                'name' => $this->tenant?->name,
            ],
            'property'        => [
                'id'   => $this->property?->id,
                'name' => $this->property?->name,
            ],
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
