<?php

namespace App\Http\Requests;

class RejectAllocationRequest extends AuthorizedFormRequest
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
            'rejection_reason' => ['nullable', 'string', 'max:500'],

        ];
    }
}
