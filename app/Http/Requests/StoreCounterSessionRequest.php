<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Counter Session Request
 *
 * Validates counter session opening data.
 * Applies to authenticated tellers, managers, and admins.
 */
class StoreCounterSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only Tellers, Managers, and Admins can open counter sessions.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $role = UserRole::tryFrom($user->role ?? '');

        return $role !== null && (
            $role === UserRole::Teller ||
            $role === UserRole::Manager ||
            $role === UserRole::Admin
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counter_id' => [
                'required',
                'integer',
                'exists:counters,id',
            ],
            'opening_floats' => [
                'required',
                'array',
                'min:1',
            ],
            'opening_floats.*.currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'opening_floats.*.amount' => [
                'required',
                'string',
                'regex:/^\d+(\.\d{1,4})?$/',
                'min:0',
            ],
            'opening_floats.*.denominations' => [
                'nullable',
                'array',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
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

        if ($this->has('counter_id')) {
            $data['counter_id'] = (int) $this->input('counter_id');
        }

        if ($this->has('opening_floats') && is_array($this->input('opening_floats'))) {
            $floats = [];
            foreach ($this->input('opening_floats') as $key => $float) {
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
            $data['opening_floats'] = $floats;
        }

        if ($this->has('notes')) {
            $data['notes'] = trim($this->input('notes'));
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
            'counter_id.required' => 'Please select a counter.',
            'counter_id.exists' => 'The selected counter does not exist.',
            'opening_floats.required' => 'Please provide opening float amounts.',
            'opening_floats.array' => 'The opening floats must be an array.',
            'opening_floats.min' => 'At least one opening float amount is required.',
            'opening_floats.*.currency_code.required' => 'Please select a currency for each float entry.',
            'opening_floats.*.currency_code.size' => 'The currency code must be exactly 3 characters.',
            'opening_floats.*.currency_code.exists' => 'The selected currency does not exist.',
            'opening_floats.*.amount.required' => 'Please enter an amount for each float entry.',
            'opening_floats.*.amount.regex' => 'The amount must be a valid number with up to 4 decimal places.',
            'opening_floats.*.amount.min' => 'The amount cannot be negative.',
            'notes.max' => 'The notes cannot exceed 1000 characters.',
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
            'counter_id' => 'counter',
            'opening_floats' => 'opening floats',
            'opening_floats.*.currency_code' => 'currency code',
            'opening_floats.*.amount' => 'amount',
            'opening_floats.*.denominations' => 'denominations',
            'notes' => 'session notes',
        ];
    }
}
