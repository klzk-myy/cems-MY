<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class LockRiskRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for locking the risk profile is required.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason must not exceed 500 characters.',
        ];
    }
}
