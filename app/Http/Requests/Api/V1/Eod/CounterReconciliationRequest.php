<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\ApiFormRequest;

class CounterReconciliationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
