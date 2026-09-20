<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TransactionType;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Rules\ValidCurrencyCode;
use App\Rules\ValidQuantity;
use App\Rules\ValidRate;
use App\Rules\ValidTill;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for transaction creation across the web, API, and
 * wizard workflows.
 *
 * Fields whose rules differ in meaning between workflows (e.g. the API's
 * stricter custom Rule classes that also enforce currency/till state) are
 * exposed as dedicated ``*Strict`` methods so each workflow can reuse the same
 * rule set without changing its validated behaviour.
 */
trait HasTransactionValidationRules
{
    /**
     * Common customer reference rule.
     *
     * The wizard additionally requires the id to be an integer.
     *
     * @return array<array-key, string>|string
     */
    protected function customerIdRule(bool $requireInteger = false): array|string
    {
        if ($requireInteger) {
            return ['required', 'integer', 'exists:customers,id'];
        }

        return 'required|exists:customers,id';
    }

    /**
     * Common transaction type rule (Buy/Sell).
     *
     * @return array<array-key, string>
     */
    protected function transactionTypeRule(): array
    {
        return ['required', Rule::enum(TransactionType::class)];
    }

    /**
     * Common currency_code rule for the web and wizard workflows.
     *
     * The API exposes a stricter variant via {@see currencyCodeRuleStrict()}.
     *
     * @return array<array-key, string>
     */
    protected function currencyCodeRule(): array
    {
        return ['required', 'string', 'exists:currencies,code'];
    }

    /**
     * Common foreign amount rule for the web and wizard workflows.
     *
     * The API exposes a stricter variant via {@see quantityRuleStrict()}.
     */
    protected function quantityRule(): string
    {
        return 'required|numeric|min:0.01|max:9999999999.9999';
    }

    /**
     * Common exchange rate rule for the web and wizard workflows.
     *
     * The API exposes a stricter variant via {@see rateRuleStrict()}.
     */
    protected function rateRule(): string
    {
        return 'required|numeric|min:0.0001|max:999999';
    }

    /**
     * Common purpose rule.
     */
    protected function purposeRule(): string
    {
        return 'required|string|max:255';
    }

    /**
     * Common source of funds rule.
     */
    protected function sourceOfFundsRule(): string
    {
        return 'required|string|max:255';
    }

    /**
     * Common source of wealth rule.
     */
    protected function sourceOfWealthRule(): string
    {
        return 'nullable|string|max:500';
    }

    /**
     * Common idempotency key rule.
     */
    protected function idempotencyKeyRule(bool $required = true): string
    {
        return $required ? 'required|string|max:100' : 'nullable|string|max:100';
    }

    /**
     * Strict currency_code rule used by the API; also checks the currency is active.
     *
     * @return array<array-key, string|ValidCurrencyCode>
     */
    protected function currencyCodeRuleStrict(): array
    {
        return ['required', 'string', new ValidCurrencyCode];
    }

    /**
     * Strict foreign amount rule used by the API.
     *
     * @return array<array-key, string|ValidQuantity>
     */
    protected function quantityRuleStrict(): array
    {
        return ['required', new ValidQuantity];
    }

    /**
     * Strict exchange rate rule used by the API.
     *
     * @return array<array-key, string|ValidRate>
     */
    protected function rateRuleStrict(): array
    {
        return ['required', new ValidRate];
    }

    /**
     * Strict till rule used by the API; validates branch scoping and open status.
     * Nullable: drawer-less bookings carry no till — custody ends at the
     * teller allocation.
     *
     * @return array<array-key, string|ValidTill>
     */
    protected function tillIdRule(): array
    {
        return ['nullable', 'string', new ValidTill];
    }

    /**
     * The counter the acting user is seated at (their open counter session).
     * When a session exists it is the booking till — a submitted counter/till
     * pointing elsewhere would move money in a drawer the user is not at, so
     * the session counter wins. Callers without a session keep honoring an
     * explicitly submitted till_id (API integrations, back-office posts).
     */
    protected function sessionCounter(): ?Counter
    {
        $user = $this->user();

        return $user === null ? null : CounterSession::openCounterForUser((int) $user->id);
    }

    /**
     * Merge the session counter's code as the booking till. Without a
     * session the submitted till_id is left untouched for the till rule.
     */
    protected function mergeSessionTill(): void
    {
        $sessionCounter = $this->sessionCounter();

        if ($sessionCounter !== null) {
            $this->merge(['till_id' => $sessionCounter->code]);
        }
    }
}
