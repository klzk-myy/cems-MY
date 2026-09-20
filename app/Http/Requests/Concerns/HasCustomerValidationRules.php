<?php

namespace App\Http\Requests\Concerns;

use App\Enums\IdType;
use App\Rules\MyKadFormatRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for customer store/update forms.
 */
trait HasCustomerValidationRules
{
    /**
     * Get the common customer validation rules.
     *
     * @param  bool  $isUpdate  When true, `id_number` is only required if present.
     */
    protected function customerValidationRules(bool $isUpdate = false): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => ['required', Rule::enum(IdType::class)],
            'id_number' => array_filter([
                $isUpdate ? 'sometimes' : null,
                $isUpdate ? null : 'required',
                'string',
                'max:50',
                new MyKadFormatRule,
            ], fn ($rule): bool => $rule !== null),
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pep_status' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the shared custom validation messages.
     */
    protected function customerValidationMessages(): array
    {
        return [
            'phone.regex' => 'The phone number format is invalid. Must be a valid Malaysian mobile number (e.g., +60123456789).',
        ];
    }
}
