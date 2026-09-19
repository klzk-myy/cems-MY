<?php

namespace App\Services\Transaction;

use App\Enums\TransactionType;
use App\Services\System\MathService;
use App\ValueObjects\QuoteConvention;

/**
 * ExchangeCalculator
 *
 * Single source of truth for converting a foreign-currency transaction amount
 * into its local-currency equivalent:
 *
 *     amount_myr = quantity / rate_unit × rate
 *
 * where `rate` is the unit-quoted rate (MYR per `rate_unit` foreign units,
 * e.g. RM 235 per 1,000,000 IDR) and `rate_unit` defaults to 1, which
 * preserves the historic per-unit convention exactly.
 *
 * Currencies flagged `rate_inverse` quote the other way: `rate` is foreign
 * units per `rate_unit` MYR (e.g. RM 1 = 4,255 IDR), and the per-unit
 * normalization inverts to rate_unit / rate.
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
     * @param  string  $quantity  Foreign amount as a numeric string.
     * @param  string  $rate  Caller-supplied exchange rate as a numeric string, quoted in $convention terms.
     * @param  int|null  $branchId  Optional branch scope (kept for API completeness).
     * @param  QuoteConvention|null  $convention  Quote convention of $rate; defaults to unit-1 direct (per-unit rate).
     * @return array{amount_myr: string, quantity: string, rate: string} The returned rate is normalized per-unit.
     */
    public function calculate(
        TransactionType $type,
        string $currencyCode,
        string $quantity,
        string $rate,
        ?int $branchId = null,
        ?QuoteConvention $convention = null
    ): array {
        // Normalize the quoted rate to per-unit (8 decimals, matching
        // the transactions.rate column precision) before multiplying, so the
        // returned rate is the exact value applied to amount_myr.
        $perUnitRate = ($convention ?? new QuoteConvention)->toPerUnit($rate);

        // Foreign → local conversion delegated to MathService (BCMath). The
        // multiply uses MathService's default scale (4), matching the prior
        // inline `multiply(quantity, rate)`.
        $amountMyr = $this->mathService->multiply($quantity, $perUnitRate);

        // Round half-up to 4 decimals to align with decimal(18,4) storage.
        // Because multiply already returns a value truncated to its default
        // scale of 4, this rounding is a no-op relative to the former inline
        // behavior and therefore preserves exact prior output.
        $amountMyr = $this->mathService->round($amountMyr, 4);

        return [
            'amount_myr' => $amountMyr,
            'quantity' => $quantity,
            'rate' => $perUnitRate,
        ];
    }
}
