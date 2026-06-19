<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class ModifyAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'new_amount' => 'required|numeric|min:0.0001',
            'is_increase' => 'required|boolean',
        ];
    }
}
