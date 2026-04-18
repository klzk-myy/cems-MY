<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Store Transaction Request
 *
 * Validates transaction creation data for buy/sell operations.
 * Applies to all authenticated tellers, managers, and admins.
 */
class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only Tellers, Managers, and Admins can create transactions.
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
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'amount' => [
                'required',
                'string',
                'regex:/^\d+(\.\d{1,4})?$/',
                'min:0.01',
            ],
            'rate' => [
                'required',
                'string',
                'regex:/^\d+(\.\d{1,6})?$/',
                'min:0.000001',
            ],
            'type' => [
                'required',
                'string',
                new Enum(TransactionType::class),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'purpose' => [
                'nullable',
                'string',
                'max:500',
            ],
            'source_of_funds' => [
                'nullable',
                'string',
                'max:255',
            ],
            'base_rate' => [
                'nullable',
                'string',
                'regex:/^\d+(\.\d{1,6})?$/',
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
        $this->merge([
            'currency_code' => $this->has('currency_code')
                ? strtoupper(trim($this->input('currency_code')))
                : null,
            'purpose' => $this->has('purpose')
                ? trim($this->input('purpose'))
                : null,
            'source_of_funds' => $this->has('source_of_funds')
                ? trim($this->input('source_of_funds'))
                : null,
            'type' => $this->has('type')
                ? ucfirst(strtolower(trim($this->input('type'))))
                : null,
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency_code.required' => 'Please select a currency.',
            'currency_code.exists' => 'The selected currency is not valid.',
            'currency_code.size' => 'The currency code must be exactly 3 characters (e.g., USD, EUR).',
            'amount.required' => 'Please enter the transaction amount.',
            'amount.regex' => 'The amount must be a valid number with up to 4 decimal places.',
            'amount.min' => 'The transaction amount must be greater than zero.',
            'rate.required' => 'Please enter the exchange rate.',
            'rate.regex' => 'The exchange rate must be a valid number with up to 6 decimal places.',
            'rate.min' => 'The exchange rate must be greater than zero.',
            'type.required' => 'Please select the transaction type (Buy or Sell).',
            'type.enum' => 'The transaction type must be either Buy or Sell.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'purpose.max' => 'The purpose cannot exceed 500 characters.',
            'source_of_funds.max' => 'The source of funds cannot exceed 255 characters.',
            'base_rate.regex' => 'The base rate must be a valid number with up to 6 decimal places.',
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
            'currency_code' => 'currency',
            'amount' => 'transaction amount',
            'rate' => 'exchange rate',
            'type' => 'transaction type',
            'customer_id' => 'customer',
            'purpose' => 'transaction purpose',
            'source_of_funds' => 'source of funds',
            'base_rate' => 'base exchange rate',
        ];
    }
}
