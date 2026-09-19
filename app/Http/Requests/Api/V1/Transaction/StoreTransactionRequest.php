<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\HasTransactionValidationRules;
use App\Models\Transaction;

class StoreTransactionRequest extends ApiFormRequest
{
    use HasTransactionValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Transaction::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => $this->customerIdRule(),
            'type' => $this->transactionTypeRule(),
            'currency_code' => $this->currencyCodeRuleStrict(),
            'quantity' => $this->quantityRuleStrict(),
            'rate' => $this->rateRuleStrict(),
            'purpose' => $this->purposeRule(),
            'source_of_funds' => $this->sourceOfFundsRule(),
            'source_of_wealth' => $this->sourceOfWealthRule(),
            'till_id' => $this->tillIdRule(),
            'idempotency_key' => $this->idempotencyKeyRule(false),
        ];
    }
}
