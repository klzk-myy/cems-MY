<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
