<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class RejectAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
