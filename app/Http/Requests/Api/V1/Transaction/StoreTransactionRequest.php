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

    /**
     * Counter selection is transparent: when the user holds an open counter
     * session its counter is the booking till and overrides any submitted
     * till_id. Callers without a session must still supply till_id.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeSessionTill();
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
