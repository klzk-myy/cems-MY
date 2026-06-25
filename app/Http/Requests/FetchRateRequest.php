<?php

namespace App\Http\Requests;

class FetchRateRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'currency_codes' => 'nullable|array',
            'currency_codes.*' => 'string|max:3|exists:currencies,code',
            'date' => 'nullable|date',
        ];
    }
}
