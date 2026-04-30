<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_floats' => 'required|array',
            'closing_floats.*.currency_id' => 'required|exists:currencies,code',
            'closing_floats.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
