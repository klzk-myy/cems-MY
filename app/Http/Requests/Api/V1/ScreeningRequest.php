<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class ScreeningRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',
        ];
    }
}
