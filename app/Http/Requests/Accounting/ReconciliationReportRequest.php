<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the request for a bank reconciliation report.
 */
class ReconciliationReportRequest extends AuthorizedFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'from' => 'required|date',
            'to' => 'required|date',
        ];
    }
}
