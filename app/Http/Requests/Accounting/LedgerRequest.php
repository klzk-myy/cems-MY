<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the ledger report filter request.
 */
class LedgerRequest extends AuthorizedFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'account_code' => 'nullable|string|exists:chart_of_accounts,account_code',
        ];
    }
}
