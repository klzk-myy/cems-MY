<?php

namespace App\Http\Requests;

class StoreSanctionEntryRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'list_id' => 'required|integer|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:Individual,Organization,Vessel,Aircraft',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|string',
        ];
    }
}
