<?php

namespace App\Http\Requests;

use App\Enums\IdType;
use Illuminate\Validation\Rule;

class QuickCreateCustomerRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => ['required', Rule::enum(IdType::class)],
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
