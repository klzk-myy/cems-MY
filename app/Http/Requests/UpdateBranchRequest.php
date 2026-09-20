<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('branches')->ignore($this->route('branch'))],
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in([Branch::TYPE_HEAD_OFFICE, Branch::TYPE_BRANCH, Branch::TYPE_SUB_BRANCH])],
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
