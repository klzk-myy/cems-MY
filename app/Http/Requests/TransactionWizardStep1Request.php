<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTransactionValidationRules;

class TransactionWizardStep1Request extends AuthorizedFormRequest
{
    use HasTransactionValidationRules;

    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransaction();
    }

    public function rules(): array
    {
        return [
            'customer_id' => $this->customerIdRule(true),
            'type' => $this->transactionTypeRule(),
            'currency_code' => $this->currencyCodeRule(),
            'amount_foreign' => $this->amountForeignRule(),
            'rate' => $this->rateRule(),
            'till_id' => ['required', 'string', 'exists:counters,code'],
            'purpose' => $this->purposeRule(),
            'source_of_funds' => $this->sourceOfFundsRule(),
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
