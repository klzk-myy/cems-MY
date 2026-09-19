<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class FundExpenseRequest extends AuthorizedFormRequest
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
            'branch_id' => 'required|integer|exists:branches,id',
            'amount_myr' => 'required|numeric|min:0.0001',
            'description' => 'nullable|string|max:500',

        ];
    }
}
