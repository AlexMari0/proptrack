<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');
        return $tenant && $this->user()->can('update', $tenant);
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')->id;

        return [
            'name'                    => ['required', 'string', 'max:255'],
            // Ignore this tenant's own email on unique check
            'email'                   => ['required', 'email', Rule::unique('tenants', 'email')->ignore($tenantId)],
            'phone'                   => ['required', 'string', 'max:20'],
            'id_card_number'          => ['required', 'string', 'digits:16'],
            'emergency_contact_name'  => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
        ];
    }
}
