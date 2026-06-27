<?php

namespace App\Http\Requests;

class SearchCustomerRequest extends AuthorizedFormRequest
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

    public function messages(): array
    {
        return [
            'query.required' => 'A search query is required.',
            'query.min' => 'The search query must be at least 2 characters.',
        ];
    }
}
