<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the profit and loss report request.
 */
class ProfitLossRequest extends AuthorizedFormRequest
{
    /**
     * All users may generate these read-only reports; authorization is
     * enforced by the reporting controllers.
     */
    public function authorize(): bool
    {
        return true;
    }

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
            'branch_id' => 'nullable|integer|exists:branches,id',
        ];
    }
}
