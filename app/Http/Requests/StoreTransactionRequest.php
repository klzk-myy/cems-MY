<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates web transaction creation data extracted from TransactionController.
 */
class StoreTransactionRequest extends AuthorizedFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Transaction::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'purpose' => trim($this->purpose ?? ''),
            'source_of_funds' => trim($this->source_of_funds ?? ''),
            'source_of_wealth' => trim($this->source_of_wealth ?? ''),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:'.TransactionType::Buy->value.','.TransactionType::Sell->value],
            'currency_code' => 'required|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
            'rate' => 'required|numeric|min:0.0001|max:999999',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'source_of_wealth' => 'nullable|string|max:500',
            'branch_id' => 'required|exists:branches,id',
            'counter_id' => 'required|exists:counters,id',
            'idempotency_key' => 'required|string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_foreign.min' => 'The transaction amount must be greater than zero.',
            'amount_foreign.max' => 'The transaction amount exceeds the maximum allowed.',
            'rate.min' => 'The exchange rate must be greater than zero.',
            'purpose.required' => 'Please specify the purpose of this transaction.',
            'source_of_funds.required' => 'Please specify the source of funds.',
            'source_of_wealth.max' => 'The source of wealth must not exceed 500 characters.',
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
            'amount_foreign' => 'foreign currency amount',
            'source_of_funds' => 'source of funds',
        ];
    }
}
