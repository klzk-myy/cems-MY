<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class FindingIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string',
            'severity' => 'nullable|string',
            'type' => 'nullable|string',
            'date_from' => 'nullable|string',
            'date_to' => 'nullable|string',
        ];
    }
}
