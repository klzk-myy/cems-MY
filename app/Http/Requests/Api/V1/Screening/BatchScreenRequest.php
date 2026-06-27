<?php

namespace App\Http\Requests\Api\V1\Screening;

use App\Http\Requests\ApiFormRequest;

class BatchScreenRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ];
    }
}
