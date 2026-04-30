<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'finding_id' => 'nullable|exists:compliance_findings,id',
            'case_type' => 'required|string',
            'assigned_to' => 'required|exists:users,id',
            'summary' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|exists:customers,id',
            'severity' => 'nullable|string',
        ];
    }
}
