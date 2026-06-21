<?php

namespace App\Http\Requests;

class UpdateCaseStatusRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'nullable|in:open,in_progress,pending_review,resolved,closed',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'The selected status is not valid.',
        ];
    }
}
