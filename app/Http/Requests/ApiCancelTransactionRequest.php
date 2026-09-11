<?php

namespace App\Http\Requests;

class ApiCancelTransactionRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the web form's field name as an alias so both surfaces send the
     * same canonical payload key.
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
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
