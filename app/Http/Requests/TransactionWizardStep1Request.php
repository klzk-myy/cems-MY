<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransactionWizardStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransaction();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'type' => ['required', new Enum(TransactionType::class)],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'amount_foreign' => ['required', 'numeric', 'min:0.01', 'max:9999999999.9999'],
            'rate' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
            'till_id' => ['required', 'string', 'exists:counters,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'source_of_funds' => ['required', 'string', 'max:255'],
            'collect_additional_details' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer',
            'amount_foreign.min' => 'Transaction amount must be at least RM 0.01',
            'amount_foreign.max' => 'Transaction amount exceeds maximum limit',
            'rate.min' => 'Exchange rate must be greater than 0',
        ];
    }
}
