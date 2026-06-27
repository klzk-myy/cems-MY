<?php

namespace App\Http\Requests;

class CustomerSearchRequest extends AuthorizedFormRequest
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
