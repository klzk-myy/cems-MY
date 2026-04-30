<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => ['required', 'in:MyKad,Passport,Others'],
            'id_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if ($this->id_type === 'MyKad' && ! preg_match('/^\d{6}-\d{2}-\d{4}$/', $value)) {
                        $fail('MyKad ID must be in format XXXXXX-XX-XXXX (e.g., 900123-01-2345)');
                    }
                },
            ],
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pep_status' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number format is invalid. Must be a valid Malaysian mobile number (e.g., +60123456789).',
        ];
    }
}
