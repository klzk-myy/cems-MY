<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class DismissFindingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
