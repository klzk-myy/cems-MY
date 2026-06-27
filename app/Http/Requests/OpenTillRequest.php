<?php

namespace App\Http\Requests;

class OpenTillRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counter_id' => 'required|exists:counters,id',
            'opening_balance_myr' => 'required|numeric|min:0',
            'currency_balances' => 'nullable|array',
            'currency_balances.*.currency_code' => 'required|string|max:3',
            'currency_balances.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
