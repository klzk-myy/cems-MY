<?php

namespace App\Services\Transaction;

use App\Enums\TransactionType;
use App\Services\System\MathService;

/**
 * ExchangeCalculator
 *
 * Single source of truth for converting a foreign-currency transaction amount
 * into its local-currency equivalent (amount_local = amount_foreign × rate).
 *
 * This centralizes the conversion that was previously inlined in three places
 * (TransactionCreationService, TransactionWizardController step 1, and
 * TransactionImportService), each calling MathService::multiply directly.
 *
 * The rate is ALWAYS the caller-provided rate (the teller-entered / import
 * rate). This service never selects rate_buy vs rate_sell; that decision
 * remains the caller's responsibility so existing financial behavior is
 * preserved exactly.
 */
class ExchangeCalculator
{
    public function __construct(protected MathService $mathService) {}

    /**
     * Convert a foreign amount to the local-currency amount.
     *
     * @param  TransactionType  $type  Buy or Sell (kept for API completeness; the rate is caller-supplied).
     * @param  string  $currencyCode  ISO currency code of the foreign amount (kept for API completeness).
     * @param  string  $amountForeign  Foreign amount as a numeric string.
     * @param  string  $rate  Caller-supplied exchange rate as a numeric string.
     * @param  int|null  $branchId  Optional branch scope (kept for API completeness).
     * @return array{amount_local: string, amount_foreign: string, rate: string}
     */
    public function calculate(
        TransactionType $type,
        string $currencyCode,
        string $amountForeign,
        string $rate,
        ?int $branchId = null
    ): array {
        // Foreign → local conversion delegated to MathService (BCMath). The
        // multiply uses MathService's default scale (4), matching the prior
        // inline `multiply(amount_foreign, rate)`.
        $amountLocal = $this->mathService->multiply($amountForeign, $rate);

        // Round half-up to 4 decimals to align with decimal(18,4) storage.
        // Because multiply already returns a value truncated to its default
        // scale of 4, this rounding is a no-op relative to the former inline
        // behavior and therefore preserves exact prior output.
        $amountLocal = $this->mathService->round($amountLocal, 4);

        return [
            'amount_local' => $amountLocal,
            'amount_foreign' => $amountForeign,
            'rate' => $rate,
        ];
    }
}
