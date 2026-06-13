<?php

namespace App\Http\Requests\Api;

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
            'rate_buy' => 'required|numeric|min:0',
            'rate_sell' => 'required|numeric|min:0',
        ];
    }
}
