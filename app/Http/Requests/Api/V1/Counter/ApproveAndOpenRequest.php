<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class ApproveAndOpenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'teller_id' => 'required|integer|exists:users,id',
            'approved_floats' => 'required|array',
            'approved_floats.*' => 'required|numeric|min:0',
            'daily_limits' => 'nullable|array',
            'daily_limits.*' => 'nullable|numeric|min:0',
        ];
    }
}
