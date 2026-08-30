<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTransactionValidationRules;
use App\Models\Transaction;

/**
 * Validates web transaction creation data extracted from TransactionController.
 */
class StoreTransactionRequest extends AuthorizedFormRequest
{
    use HasTransactionValidationRules;

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => $this->customerIdRule(),
            'type' => $this->transactionTypeRule(),
            'currency_code' => $this->currencyCodeRule(),
            'amount_foreign' => $this->amountForeignRule(),
            'rate' => $this->rateRule(),
            'purpose' => $this->purposeRule(),
            'source_of_funds' => $this->sourceOfFundsRule(),
            'source_of_wealth' => $this->sourceOfWealthRule(),
            'branch_id' => 'required|exists:branches,id',
            'counter_id' => 'required|exists:counters,id',
            'idempotency_key' => $this->idempotencyKeyRule(),
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
