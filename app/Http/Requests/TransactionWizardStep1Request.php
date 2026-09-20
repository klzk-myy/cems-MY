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

    /**
     * The booking till is the counter the teller is seated at — resolved
     * from their open session so the wizard never asks for it. Without a
     * session a submitted till_id is still honored.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeSessionTill();
    }

    public function rules(): array
    {
        return [
            'customer_id' => $this->customerIdRule(true),
            'type' => $this->transactionTypeRule(),
            'currency_code' => $this->currencyCodeRule(),
            'quantity' => $this->quantityRule(),
            'rate' => $this->rateRule(),
            'till_id' => $this->tillIdRule(),
            'purpose' => $this->purposeRule(),
            'source_of_funds' => $this->sourceOfFundsRule(),
            'collect_additional_details' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer',
            'quantity.min' => 'Transaction amount must be at least RM 0.01',
            'quantity.max' => 'Transaction amount exceeds maximum limit',
            'rate.min' => 'Exchange rate must be greater than 0',
            'till_id.required' => 'You are not seated at an open counter. Ask your manager to allocate stock and open your session first.',
        ];
    }
}
