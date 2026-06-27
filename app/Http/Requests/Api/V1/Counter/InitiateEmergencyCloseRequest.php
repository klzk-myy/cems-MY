<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class InitiateEmergencyCloseRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
