<?php

namespace App\Http\Requests;

class ConfirmTransactionRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'confirmation_action' => 'required|in:confirm,reject',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation_action.required' => 'Please select confirm or reject.',
            'confirmation_action.in' => 'Action must be confirm or reject.',
            'notes.string' => 'Notes must be a string.',
            'notes.max' => 'Notes must not exceed 500 characters.',
        ];
    }
}
