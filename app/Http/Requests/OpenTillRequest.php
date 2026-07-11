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
            'till_id' => 'required|string|exists:counters,code',
            'currency_code' => 'required|string|exists:currencies,code',
            'opening_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
