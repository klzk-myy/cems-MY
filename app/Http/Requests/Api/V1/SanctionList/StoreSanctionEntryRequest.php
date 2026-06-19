<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\ApiFormRequest;

class StoreSanctionEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'list_id' => 'required|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
        ];
    }
}
