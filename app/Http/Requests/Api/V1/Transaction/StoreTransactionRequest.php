<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\ApiFormRequest;

class StoreTransactionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:Buy,Sell'],
            'currency_code' => 'required|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
            'rate' => 'required|numeric|min:0.0001|max:999999',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'till_id' => 'required|string',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }
}
