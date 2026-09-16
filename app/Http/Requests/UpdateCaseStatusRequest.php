<?php

namespace App\Http\Requests;

use App\Enums\CaseResolution;
use App\Enums\ComplianceCaseStatus;
use Illuminate\Validation\Rules\Enum as EnumRule;

class UpdateCaseStatusRequest extends AuthorizedFormRequest
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
            'status' => ['required_without:assigned_to', new EnumRule(ComplianceCaseStatus::class)],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'resolution' => ['required_if:status,'.ComplianceCaseStatus::Closed->value, 'nullable', new EnumRule(CaseResolution::class)],
            'notes' => 'nullable|string',
        ];
    }
}
