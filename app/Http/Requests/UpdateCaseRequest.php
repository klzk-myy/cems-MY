<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string',
            'case_summary' => 'nullable|string|max:1000',
        ];
    }
}
