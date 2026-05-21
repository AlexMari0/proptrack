<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required|uuid|exists:properties,id',
            'category'    => 'required|string|in:maintenance,billing,other',
            'priority'    => 'required|string|in:low,medium,high',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
        ];
    }
}
