<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class MyActiveAllocationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_code' => 'required|string|size:3',
        ];
    }
}
