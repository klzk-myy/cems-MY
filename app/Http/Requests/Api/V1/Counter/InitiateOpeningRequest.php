<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class InitiateOpeningRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_floats' => 'required|array',
            'requested_floats.*' => 'required|numeric|min:0.0001',
        ];
    }
}
