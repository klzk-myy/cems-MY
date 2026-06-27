<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class CaseIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string',
            'type' => 'nullable|string',
            'severity' => 'nullable|string',
            'assigned_to' => 'nullable|integer',
        ];
    }
}
