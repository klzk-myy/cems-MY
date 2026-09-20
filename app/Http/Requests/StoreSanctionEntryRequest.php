<?php

namespace App\Http\Requests;

use App\Enums\EntityType;
use Illuminate\Validation\Rule;

class StoreSanctionEntryRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list_id' => 'required|integer|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|string',
        ];
    }
}
