<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class ReconciliationIndexRequest extends AuthorizedFormRequest
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
            'account_code' => 'nullable|string|exists:chart_of_accounts,account_code',
            'account' => 'nullable|string|exists:chart_of_accounts,account_code',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',

        ];
    }
}
