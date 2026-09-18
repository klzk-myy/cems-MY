<?php

namespace App\Http\Requests;

class RemitBranchPoolRequest extends AuthorizedFormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:500'],

        ];
    }
}
