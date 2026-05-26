<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentUser = $this->user();
        if (!$currentUser) {
            return false;
        }

        $targetRole = $this->input('role');

        if ($currentUser->hasRole('admin')) {
            return true;
        }

        if ($currentUser->hasRole('owner')) {
            return in_array($targetRole, ['agent', 'tenant']);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,owner,agent,tenant'],
        ];
    }
}
