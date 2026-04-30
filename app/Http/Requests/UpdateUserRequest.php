<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($this->route('user'))],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => ['required', Rule::in([
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])],
            'is_active' => 'required|boolean',
        ];
    }
}
