<?php

namespace App\Http\Requests;

class ReverseTransactionRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
            'confirm_understanding' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reversal reason is required for AML audit compliance. Please provide a detailed explanation of why this transaction is being reversed.',
            'reason.min' => 'Reversal reason must be at least 20 characters for AML audit compliance. Please provide a detailed explanation of why this transaction is being reversed.',
        ];
    }
}
