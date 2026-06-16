<?php

namespace App\Http\Requests\Api\V1\Rate;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateRateRequest extends AuthorizedFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate' => 'required|numeric|min:0.0001',
            'currency_code' => 'required|string|size:3',
            'type' => 'required|in:buy,sell',
        ];
    }
}
