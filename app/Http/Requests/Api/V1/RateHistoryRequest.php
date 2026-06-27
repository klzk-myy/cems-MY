<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class RateHistoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => 'nullable|integer|min:1|max:365',
        ];
    }
}
