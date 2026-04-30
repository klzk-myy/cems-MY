<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_codes' => 'nullable|array',
            'currency_codes.*' => 'string|max:3|exists:currencies,code',
            'date' => 'nullable|date',
        ];
    }
}
