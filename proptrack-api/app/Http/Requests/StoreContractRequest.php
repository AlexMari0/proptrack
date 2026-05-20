<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Contract;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contract::class);
    }

    public function rules(): array
    {
        return [
            'tenant_id'      => ['required', 'uuid', 'exists:tenants,id'],
            'property_id'    => ['required', 'uuid', 'exists:properties,id'],
            'start_date'     => ['required', 'date', 'after_or_equal:today'],
            'end_date'       => ['required', 'date', 'after:start_date'],
            'monthly_rent'   => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['required', 'integer', 'min:0'],
            'billing_date'   => ['required', 'integer', 'min:1', 'max:28'],
        ];
    }
}
