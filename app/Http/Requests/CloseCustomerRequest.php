<?php

namespace App\Http\Requests;

class CloseCustomerRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Closure decisions must carry an auditable justification,
            // mirroring the freeze workflow.
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
