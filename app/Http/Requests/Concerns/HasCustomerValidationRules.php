<?php

namespace App\Http\Requests\Concerns;

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
            'id_type' => ['required', 'in:MyKad,Passport,Others'],
            'id_number' => [
                $isUpdate ? 'sometimes' : 'required',
                'required',
                'string',
                'max:50',
                $this->myKadFormatRule(),
            ],
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

    /**
     * Closure rule validating MyKad number format.
     */
    private function myKadFormatRule(): \Closure
    {
        return function ($attribute, $value, $fail): void {
            if ($this->id_type === 'MyKad' && ! preg_match('/^\d{6}-\d{2}-\d{4}$/', $value)) {
                $fail('MyKad ID must be in format XXXXXX-XX-XXXX (e.g., 900123-01-2345)');
            }
        };
    }
}
