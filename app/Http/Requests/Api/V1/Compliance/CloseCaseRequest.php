<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class CloseCaseRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'resolution' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'resolution.required' => 'A resolution is required to close a case.',
            'resolution.string' => 'Resolution must be a string.',
        ];
    }
}
