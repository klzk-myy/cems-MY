<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'parent_id' => 'nullable|exists:branches,id',
        ];
    }
}
