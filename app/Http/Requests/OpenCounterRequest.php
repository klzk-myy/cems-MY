<?php

namespace App\Http\Requests;

class OpenCounterRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'opening_floats' => 'required|array',
            'opening_floats.*.currency_id' => 'required|exists:currencies,code',
            'opening_floats.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
