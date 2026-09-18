<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Http\Requests\ApiFormRequest;

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
            'status' => 'nullable|in:'.implode(',', array_column(EddStatus::cases(), 'value')),
            'risk_level' => 'nullable|in:'.implode(',', array_column(EddRiskLevel::cases(), 'value')),
        ];
    }
}
