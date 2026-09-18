<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Http\Requests\ApiFormRequest;

class CaseIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:'.implode(',', array_column(ComplianceCaseStatus::cases(), 'value')),
            'type' => 'nullable|string|max:100',
            'severity' => 'nullable|in:critical,high,medium,low',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ];
    }
}
