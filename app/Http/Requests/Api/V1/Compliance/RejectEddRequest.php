<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class RejectEddRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason must not exceed 1000 characters.',
        ];
    }
}
