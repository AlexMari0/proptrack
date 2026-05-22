<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'tenant'         => [
                'id'   => $this->tenant?->id,
                'name' => $this->tenant?->name,
            ],
            'property'       => [
                'id'      => $this->property?->id,
                'name'    => $this->property?->name,
                'address' => $this->property?->address,
            ],
            'start_date'     => $this->start_date?->toDateString(),
            'end_date'       => $this->end_date?->toDateString(),
            'monthly_rent'   => $this->monthly_rent,
            'deposit_amount' => $this->deposit_amount,
            'billing_date'   => $this->billing_date,
            'terminated_at'  => $this->terminated_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
