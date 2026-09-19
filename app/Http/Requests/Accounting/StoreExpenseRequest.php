<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function rules(): array
    {
        // All-branch users must pick the branch explicitly; branch-scoped
        // users may omit it (the controller stamps their own branch).
        $branchRequired = (bool) $this->user()?->role->canManageAllBranches();

        return [
            'branch_id' => [$branchRequired ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'account_code' => [
                'required',
                'string',
                Rule::exists('chart_of_accounts', 'account_code')
                    ->where('account_type', 'Expense')
                    ->where('is_active', true),
            ],
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'amount_myr' => 'required|numeric|min:0.0001',
            'expense_date' => 'nullable|date',
        ];
    }
}
