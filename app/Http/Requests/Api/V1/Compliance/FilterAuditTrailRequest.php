<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class FilterAuditTrailRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'case_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'Per page must be an integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page must not exceed 100.',
            'from_date.date' => 'From date must be a valid date.',
            'to_date.date' => 'To date must be a valid date.',
            'to_date.after_or_equal' => 'To date must be after or equal to from date.',
            'case_id.integer' => 'Case ID must be an integer.',
        ];
    }
}
