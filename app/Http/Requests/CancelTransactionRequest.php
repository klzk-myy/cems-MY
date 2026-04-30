<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:20', 'max:1000'],
            'confirm_understanding' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.min' => 'Cancellation reason must be at least 20 characters for AML audit compliance. Please provide a detailed explanation of why this transaction is being cancelled.',
        ];
    }
}
