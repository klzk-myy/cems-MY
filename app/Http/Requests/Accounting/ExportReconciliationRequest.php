<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the request for exporting bank reconciliation data.
 */
class ExportReconciliationRequest extends AuthorizedFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'from' => 'required|date',
            'to' => 'required|date',
        ];
    }
}
