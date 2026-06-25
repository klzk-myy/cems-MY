<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class CloseCaseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'resolution' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}
