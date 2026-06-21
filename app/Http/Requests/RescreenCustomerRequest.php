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
}
