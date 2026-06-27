<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TransactionType;
use App\Http\Requests\AuthorizedFormRequest;

class StoreTransactionRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'till_id' => 'required|string',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Customer does not exist.',
            'type.required' => 'Transaction type is required.',
            'type.in' => 'Transaction type must be Buy or Sell.',
            'currency_code.required' => 'Currency is required.',
            'currency_code.exists' => 'Currency does not exist.',
            'amount_foreign.required' => 'Amount is required.',
            'amount_foreign.numeric' => 'Amount must be a valid number.',
            'amount_foreign.min' => 'Amount must be at least 0.01.',
            'rate.required' => 'Exchange rate is required.',
            'rate.numeric' => 'Exchange rate must be a valid number.',
            'purpose.required' => 'Purpose of transaction is required.',
            'source_of_funds.required' => 'Source of funds is required.',
            'till_id.required' => 'Till ID is required.',
        ];
    }
}
