<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for user store/update forms.
 */
trait HasUserValidationRules
{
    /**
     * Get the common user validation rules.
     *
     * @param  bool  $isUpdate  When true, unique rules ignore the route model.
     */
    protected function userValidationRules(bool $isUpdate = false): array
    {
        $unique = fn (string $column) => $isUpdate
            ? Rule::unique('users', $column)->ignore($this->route('user'))
            : Rule::unique('users', $column);

        return [
            'username' => ['required', 'string', 'max:50', $unique('username')],
            'email' => ['required', 'email', 'max:255', $unique('email')],
            'role' => ['required', Rule::in([
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])],
        ];
    }
}
