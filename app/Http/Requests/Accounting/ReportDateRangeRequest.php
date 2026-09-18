<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;

class ReportDateRangeRequest extends AuthorizedFormRequest
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
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',

        ];
    }
}
