<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->activeContract;

        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'email'                   => $this->email,
            'phone'                   => $this->phone,
            'id_card_number'          => $this->id_card_number,
            'emergency_contact_name'  => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'active_contract'         => $contract ? [
                'id'           => $contract->id,
                'status'       => $contract->status,
                'start_date'   => $contract->start_date?->toDateString(),
                'end_date'     => $contract->end_date?->toDateString(),
                'monthly_rent' => $contract->monthly_rent,
                'property'     => [
                    'id'   => $contract->property?->id,
                    'name' => $contract->property?->name,
                ],
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
