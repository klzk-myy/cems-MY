<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\HasTransactionValidationRules;
use App\Models\Counter;
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
     * counter_id/till_id. Callers without a session may supply counter_id
     * (canonical) or the legacy till_id counter code — both resolve to the
     * counter code expected downstream.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('till_id') && $this->filled('counter_id')) {
            $counterId = $this->input('counter_id');
            $counter = is_numeric($counterId)
                ? Counter::query()->find((int) $counterId)
                : null;

            if ($counter instanceof Counter) {
                $this->merge(['till_id' => $counter->code]);
            }
        }

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
            'counter_id' => ['nullable', 'integer', 'exists:counters,id'],
            'till_id' => $this->tillIdRule(),
            'idempotency_key' => $this->idempotencyKeyRule(false),
        ];
    }
}
