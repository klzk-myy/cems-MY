<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasCustomerValidationRules;
use App\Models\Customer;

class StoreCustomerRequest extends AuthorizedFormRequest
{
    use HasCustomerValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        return $this->customerValidationRules();
    }

    public function messages(): array
    {
        return $this->customerValidationMessages();
    }
}
