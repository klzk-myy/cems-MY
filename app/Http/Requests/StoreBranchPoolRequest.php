<?php

namespace App\Http\Requests;

class StoreBranchPoolRequest extends AuthorizedFormRequest
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
            'branch_id' => [$this->user()->role->canManageAllBranches() ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],

        ];
    }
}
