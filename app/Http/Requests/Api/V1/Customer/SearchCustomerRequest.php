<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\ApiFormRequest;

class SearchCustomerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2',
        ];
    }
}
