<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class CustomerIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
            'risk_rating' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'pep_status' => 'nullable|boolean',
        ];
    }
}
