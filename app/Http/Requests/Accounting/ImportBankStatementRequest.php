<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class ImportBankStatementRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'lines' => 'required|array|min:1',
            'lines.*.date' => 'required|date',
            'lines.*.reference' => 'nullable|string|max:255',
            'lines.*.description' => 'required|string|max:500',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ];
    }
}
