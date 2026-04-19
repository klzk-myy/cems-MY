<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Close Counter Session Request
 *
 * Validates counter session closing data.
 * Applies to authenticated tellers, managers, and admins.
 */
class CloseCounterSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only Tellers, Managers, and Admins can close counter sessions.
     * Users can only close sessions they opened or have handover permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $role = UserRole::tryFrom($user->role ?? '');

        if ($role === null) {
            return false;
        }

        // Admins and Managers can close any session
        if ($role === UserRole::Admin || $role === UserRole::Manager) {
            return true;
        }

        // Tellers can close their own sessions (additional check in controller)
        if ($role === UserRole::Teller) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'closing_floats' => [
                'required',
                'array',
                'min:1',
            ],
            'closing_floats.*.currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'closing_floats.*.amount' => [
                'required',
                'string',
                'regex:/^\d+(\.\d{1,4})?$/',
                'min:0',
            ],
            'closing_floats.*.denominations' => [
                'nullable',
                'array',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'discrepancy_reason' => [
                'nullable',
                'string',
                'max:500',
                'required_with:has_discrepancy',
            ],
            'has_discrepancy' => [
                'boolean',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trims strings and sanitizes inputs.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('closing_floats') && is_array($this->input('closing_floats'))) {
            $floats = [];
            foreach ($this->input('closing_floats') as $key => $float) {
                $floats[$key] = [
                    'currency_code' => isset($float['currency_code'])
                        ? strtoupper(trim($float['currency_code']))
                        : null,
                    'amount' => isset($float['amount'])
                        ? trim($float['amount'])
                        : null,
                    'denominations' => $float['denominations'] ?? null,
                ];
            }
            $data['closing_floats'] = $floats;
        }

        if ($this->has('notes')) {
            $data['notes'] = trim($this->input('notes'));
        }

        if ($this->has('discrepancy_reason')) {
            $data['discrepancy_reason'] = trim($this->input('discrepancy_reason'));
        }

        $this->merge($data);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closing_floats.required' => 'Please provide closing float amounts.',
            'closing_floats.array' => 'The closing floats must be an array.',
            'closing_floats.min' => 'At least one closing float amount is required.',
            'closing_floats.*.currency_code.required' => 'Please select a currency for each float entry.',
            'closing_floats.*.currency_code.size' => 'The currency code must be exactly 3 characters.',
            'closing_floats.*.currency_code.exists' => 'The selected currency does not exist.',
            'closing_floats.*.amount.required' => 'Please enter an amount for each float entry.',
            'closing_floats.*.amount.regex' => 'The amount must be a valid number with up to 4 decimal places.',
            'closing_floats.*.amount.min' => 'The amount cannot be negative.',
            'notes.max' => 'The notes cannot exceed 1000 characters.',
            'discrepancy_reason.required_with' => 'Please provide a reason for the discrepancy.',
            'discrepancy_reason.max' => 'The discrepancy reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'closing_floats' => 'closing floats',
            'closing_floats.*.currency_code' => 'currency code',
            'closing_floats.*.amount' => 'amount',
            'closing_floats.*.denominations' => 'denominations',
            'notes' => 'session notes',
            'discrepancy_reason' => 'discrepancy reason',
            'has_discrepancy' => 'discrepancy flag',
        ];
    }
}
