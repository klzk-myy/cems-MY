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

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'id_type.required' => 'An ID type must be selected.',
            'id_type.in' => 'The ID type must be MyKad, Passport, or Others.',
            'id_number.required' => 'An ID number is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'nationality.required' => 'Nationality is required.',
        ];
    }
}
