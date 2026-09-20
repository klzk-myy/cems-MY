<?php

namespace App\Http\Requests;

use App\Enums\IdType;
use App\Http\Requests\Concerns\HasTransactionValidationRules;
use App\Models\Counter;
use App\Models\Transaction;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates web transaction creation data extracted from TransactionController.
 */
class StoreTransactionRequest extends AuthorizedFormRequest
{
    use HasTransactionValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Transaction::class);
    }

    /**
     * Maps the posted counter to the canonical till_id so downstream code
     * (controller, services) sees the same shape as the API payload.
     *
     * Counter selection is transparent to the teller: when they hold an open
     * counter session, that counter is the booking till and any submitted
     * counter_id/till_id is overridden. Without a session (back-office posts)
     * the explicit values still apply.
     */
    protected function prepareForValidation(): void
    {
        $merged = [
            'purpose' => trim($this->purpose ?? ''),
            'source_of_funds' => trim($this->source_of_funds ?? ''),
            'source_of_wealth' => trim($this->source_of_wealth ?? ''),
        ];

        $sessionCounter = $this->sessionCounter();

        if ($sessionCounter instanceof Counter) {
            $merged['counter_id'] = $sessionCounter->id;
            $merged['till_id'] = $sessionCounter->code;
        } elseif ($this->filled('till_id')) {
            // till_id is canonical for booking — resolve it once, normalize
            // the stored value to the counter code, and re-derive counter_id
            // so a submitted pair pointing at different counters cannot
            // store a divergent (till_id, counter_id) on the transaction.
            $till = $this->input('till_id');
            $counter = is_scalar($till) ? Counter::findByCodeOrId((string) $till) : null;
            $merged['counter_id'] = $counter?->id;
            if ($counter instanceof Counter) {
                $merged['till_id'] = $counter->code;
            }
        } elseif ($this->filled('counter_id')) {
            $counterId = $this->input('counter_id');
            $counter = is_numeric($counterId)
                ? Counter::query()->find((int) $counterId)
                : null;
            $merged['till_id'] = $counter instanceof Counter
                ? $counter->code
                : (is_scalar($counterId) ? (string) $counterId : '');
        }

        $this->merge($merged);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // customer_id stays optional on the web form: the customer section
            // either resolves an existing record (auto-load by ID/name match)
            // or carries enough fields to register one inline. Tier-dependent
            // field requirements (CDD thresholds) are enforced in
            // TransactionCreationService once amount_myr is known.
            'customer_id' => 'nullable|exists:customers,id',
            'full_name' => 'required_without:customer_id|nullable|string|max:255',
            'id_type' => ['required_without:customer_id', 'nullable', Rule::enum(IdType::class)],
            'id_number' => 'required_without:customer_id|nullable|string|max:50',
            'date_of_birth' => 'required_without:customer_id|nullable|date|before:today',
            'nationality' => 'required_without:customer_id|nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            // 14A.10.3(c): residential/mailing address is required at every
            // CDD tier, so a new registration must always carry it. Gap-fill
            // for existing records is still enforced post-calculation by the
            // service once the exact tier is known.
            'address' => 'required_without:customer_id|nullable|string|max:1000',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'type' => $this->transactionTypeRule(),
            'currency_code' => $this->currencyCodeRuleStrict(),
            'quantity' => $this->quantityRuleStrict(),
            'rate' => $this->rateRuleStrict(),
            'purpose' => $this->purposeRule(),
            'source_of_funds' => $this->sourceOfFundsRule(),
            'source_of_wealth' => $this->sourceOfWealthRule(),
            'branch_id' => 'required|exists:branches,id',
            // Optional: the teller's open session supplies the counter; an
            // explicit value is only honored when no session exists.
            'counter_id' => 'nullable|exists:counters,id',
            // till_id is mapped from counter_id in prepareForValidation() and
            // validated strictly (branch-scoped, active counter) — same rule
            // the API applies to its own till_id input. Nullable: bookings
            // without a drawer keep custody at the teller allocation.
            'till_id' => $this->tillIdRule(),
            'idempotency_key' => $this->idempotencyKeyRule(),
        ];
    }

    /**
     * Cross-field check: the posted branch must be the branch the selected
     * counter belongs to. Without it a client could submit branch_id=A with
     * a counter from branch B — the booking would silently land on B's till
     * while the payload claimed A.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $counter = $this->filled('counter_id')
                ? Counter::query()->find((int) $this->input('counter_id'))
                : null;

            if ($counter instanceof Counter
                && $this->filled('branch_id')
                && (int) $counter->branch_id !== (int) $this->input('branch_id')) {
                $validator->errors()->add(
                    'branch_id',
                    'The selected branch does not match the counter\'s branch.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'The transaction amount must be greater than zero.',
            'quantity.max' => 'The transaction amount exceeds the maximum allowed.',
            'rate.min' => 'The exchange rate must be greater than zero.',
            'purpose.required' => 'Please specify the purpose of this transaction.',
            'source_of_funds.required' => 'Please specify the source of funds.',
            'source_of_wealth.max' => 'The source of wealth must not exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'foreign currency amount',
            'source_of_funds' => 'source of funds',
        ];
    }
}
