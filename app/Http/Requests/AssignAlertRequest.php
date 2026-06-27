<?php

namespace App\Http\Requests;

class AssignAlertRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => 'required|exists:users,id',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
