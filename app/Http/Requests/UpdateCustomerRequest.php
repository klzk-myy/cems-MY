<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasCustomerValidationRules;

class UpdateCustomerRequest extends AuthorizedFormRequest
{
    use HasCustomerValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    public function rules(): array
    {
        return array_merge($this->customerValidationRules(isUpdate: true), [
            'risk_rating' => ['nullable', 'in:Low,Medium,High'],
            'is_active' => 'sometimes|boolean',
        ]);
    }

    public function messages(): array
    {
        return $this->customerValidationMessages();
    }
}
