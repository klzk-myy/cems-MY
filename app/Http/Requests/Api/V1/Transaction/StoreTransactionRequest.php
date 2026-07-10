<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\ApiFormRequest;
use App\Rules\ValidAmountForeign;
use App\Rules\ValidCurrencyCode;
use App\Rules\ValidRate;
use App\Rules\ValidTill;

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
            'currency_code' => ['required', 'string', new ValidCurrencyCode],
            'amount_foreign' => ['required', new ValidAmountForeign],
            'rate' => ['required', new ValidRate],
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'till_id' => ['required', 'string', new ValidTill],
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }
}
