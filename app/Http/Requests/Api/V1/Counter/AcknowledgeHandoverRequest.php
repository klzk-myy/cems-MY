<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class AcknowledgeHandoverRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
