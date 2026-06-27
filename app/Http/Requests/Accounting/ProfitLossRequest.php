<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

/**
 * Validates the profit and loss report request.
 */
class ProfitLossRequest extends AuthorizedFormRequest
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
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ];
    }
}
