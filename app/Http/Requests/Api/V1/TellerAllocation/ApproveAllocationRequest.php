<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class ApproveAllocationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => 'required|numeric|min:0.0001',
            'daily_limit_myr' => 'nullable|numeric|min:0',
        ];
    }
}
