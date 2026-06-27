<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class EddIndexRequest extends ApiFormRequest
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
            'risk_level' => 'nullable|string',
        ];
    }
}
