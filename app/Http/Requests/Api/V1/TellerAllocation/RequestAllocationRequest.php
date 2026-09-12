<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class RequestAllocationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'currency_code' => 'required|string|size:3',
            'requested_amount' => 'required|numeric|min:0.0001',
            'counter_id' => 'nullable|integer|exists:counters,id',
        ];
    }
}
