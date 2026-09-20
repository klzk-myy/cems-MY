<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class EddIndexRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => ['nullable', Rule::enum(EddStatus::class)],
            'risk_level' => ['nullable', Rule::enum(EddRiskLevel::class)],
        ];
    }
}
