<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class DismissFindingRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A dismissal reason is required.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason must not exceed 500 characters.',
        ];
    }
}
