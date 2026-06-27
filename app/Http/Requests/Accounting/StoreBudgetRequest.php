<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class StoreBudgetRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_code' => 'required|string',
            'budgets' => 'required|array|min:1',
            'budgets.*.account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'budgets.*.amount' => 'required|numeric|min:0',
        ];
    }
}
