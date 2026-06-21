<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\ApiFormRequest;

class UpdateSanctionEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'entity_name' => 'nullable|string|max:255',
            'entity_type' => 'nullable|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
