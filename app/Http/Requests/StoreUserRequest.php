<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'password_confirmation' => 'required',
            'role' => ['required', Rule::in([
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])],
        ];
    }
}
