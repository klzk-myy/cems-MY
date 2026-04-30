<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCtosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
            'reporting_officer_notes' => 'nullable|string|max:2000',
        ];
    }
}
