<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_of_funds' => 'required|string',
            'source_of_funds_description' => 'nullable|string',
            'purpose_of_transaction' => 'required|string',
            'business_justification' => 'nullable|string',
            'employment_status' => 'nullable|string',
            'employer_name' => 'nullable|string|max:200',
            'employer_address' => 'nullable|string|max:500',
            'annual_income_range' => 'nullable|string',
            'estimated_net_worth' => 'nullable|string',
            'source_of_wealth' => 'nullable|string',
            'source_of_wealth_description' => 'nullable|string',
            'additional_information' => 'nullable|string',
        ];
    }
}
