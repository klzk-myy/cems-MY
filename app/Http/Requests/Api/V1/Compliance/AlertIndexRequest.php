<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AlertIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'priority' => 'nullable|string',
            'assigned' => 'nullable|string',
            'status' => 'nullable|string',
        ];
    }
}
