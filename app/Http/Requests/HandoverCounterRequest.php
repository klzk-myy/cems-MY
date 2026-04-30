<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandoverCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
            'physical_counts' => 'required|array',
            'physical_counts.*.currency_id' => 'required|exists:currencies,code',
            'physical_counts.*.amount' => 'required|numeric|min:0',
            'variance_notes' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
