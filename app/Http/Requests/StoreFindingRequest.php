<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'finding_type' => 'required|string|max:100',
            'description' => 'required|string|max:5000',
            'severity' => 'required|in:low,medium,high,critical',
        ];
    }
}
