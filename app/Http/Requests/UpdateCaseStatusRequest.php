<?php

namespace App\Http\Requests;

class UpdateCaseStatusRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:open,in_progress,pending_review,resolved,closed',
            'notes' => 'nullable|string',
        ];
    }
}
