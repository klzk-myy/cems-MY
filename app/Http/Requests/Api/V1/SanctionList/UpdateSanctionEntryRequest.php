<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateSanctionEntryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_name' => 'nullable|string|max:255',
            'entity_type' => ['nullable', Rule::enum(EntityType::class)],
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
            'status' => ['nullable', Rule::enum(SanctionStatus::class)],
        ];
    }
}
