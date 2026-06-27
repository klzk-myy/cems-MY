<?php

namespace App\Http\Requests;

class UpdateSanctionEntryRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_name' => 'required|string|max:255',
            'list_source' => 'nullable|string|max:255',
            'entity_type' => 'required|in:Individual,Organization,Vessel,Aircraft',
            'reference_number' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'date_listed' => 'nullable|date',
            'aliases' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'details' => 'nullable|string',
        ];
    }
}
