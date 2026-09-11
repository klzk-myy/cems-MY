<?php

namespace App\Http\Requests;

class CancelTransactionRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizes the deprecated `cancellation_reason` form field to the
     * canonical `reason` key used by the API surface.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('reason') && $this->filled('cancellation_reason')) {
            $this->merge(['reason' => $this->input('cancellation_reason')]);
        }
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
            'reason.required' => 'A cancellation reason is required for AML audit compliance. Please provide a detailed explanation of why this transaction is being cancelled.',
            'reason.min' => 'Cancellation reason must be at least 20 characters for AML audit compliance. Please provide a detailed explanation of why this transaction is being cancelled.',
        ];
    }
}
