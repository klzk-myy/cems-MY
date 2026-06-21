<?php

namespace App\Http\Requests;

class RescreenCustomerRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'A customer must be selected for rescreening.',
            'customer_id.exists' => 'The selected customer does not exist.',
        ];
    }
}
