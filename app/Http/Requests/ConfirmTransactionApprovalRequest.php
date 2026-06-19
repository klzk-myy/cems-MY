<?php

namespace App\Http\Requests;

class ConfirmTransactionApprovalRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'confirmation_action' => 'required|in:confirm,reject',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
