<?php

namespace App\Http\Requests;

class QuickCreateCustomerRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|in:MyKad,Passport,Others',
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
