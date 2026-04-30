<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverrideRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_buy' => 'required|numeric|min:0.0001',
            'rate_sell' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string|max:500',
            'effective_date' => 'nullable|date',
        ];
    }
}
