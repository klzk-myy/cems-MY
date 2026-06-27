<?php

namespace App\Http\Requests\Api\V1\Sanction;

use App\Http\Requests\ApiFormRequest;

class SearchSanctionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
        ];
    }
}
