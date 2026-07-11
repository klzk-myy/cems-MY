<?php

namespace App\Http\Requests;

class CloseTillRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'till_id' => 'required|exists:counters,id',
            'currency_code' => 'required|string|exists:currencies,code',
            'closing_balance' => 'required|numeric|min:0',
            'difference_notes' => 'nullable|string|max:500',
        ];
    }
}
