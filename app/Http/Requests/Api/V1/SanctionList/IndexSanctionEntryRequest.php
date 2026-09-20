<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Enums\SanctionStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexSanctionEntryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'list_id' => 'integer|exists:sanction_lists,id',
            'search' => 'string|max:255',
            'status' => Rule::in([...array_column(SanctionStatus::cases(), 'value'), 'all']),
        ];
    }
}
