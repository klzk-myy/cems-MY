<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => 'required|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_code' => [
                'required',
                'string',
                Rule::exists('chart_of_accounts', 'account_code')
                    ->where('allow_journal', true),
            ],
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ];
    }
}
