<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class StoreExpenseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => 'nullable|integer|exists:branches,id',
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.0001',
            'expense_date' => 'nullable|date',
        ];
    }
}
