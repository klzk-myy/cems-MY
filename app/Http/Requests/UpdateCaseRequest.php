<?php

namespace App\Http\Requests;

class UpdateCaseRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string',
            'case_summary' => 'nullable|string|max:1000',
        ];
    }
}
