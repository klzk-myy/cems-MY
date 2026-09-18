<?php

namespace App\Http\Requests;

class ApproveAllocationRequest extends AuthorizedFormRequest
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
            'approved_amount' => ['required', 'numeric', 'min:0.0001'],
            'daily_limit_myr' => ['nullable', 'numeric', 'min:0'],

        ];
    }
}
