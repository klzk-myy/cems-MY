<?php

namespace App\Http\Requests;

use App\Enums\SanctionListType;
use App\Enums\SanctionSourceFormat;
use Illuminate\Validation\Rule;

class StoreSanctionSourceRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'list_type' => ['required', Rule::enum(SanctionListType::class)],
            'source_url' => 'nullable|url|max:1000',
            'source_format' => ['nullable', 'required_with:source_url', Rule::enum(SanctionSourceFormat::class)],
        ];
    }
}
