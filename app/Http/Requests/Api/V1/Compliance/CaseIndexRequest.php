<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\FindingSeverity;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

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
            'status' => ['nullable', Rule::enum(ComplianceCaseStatus::class)],
            'type' => 'nullable|string|max:100',
            'severity' => ['nullable', Rule::enum(FindingSeverity::class)],
            'assigned_to' => 'nullable|integer|exists:users,id',
        ];
    }
}
