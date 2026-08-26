<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the trial balance report request.
 */
class TrialBalanceRequest extends AuthorizedFormRequest
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
            'as_of_date' => 'nullable|date',
        ];
    }
}
