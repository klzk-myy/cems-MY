<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class TransactionIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
