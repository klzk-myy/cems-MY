<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rate Management Service
 *
 * Handles daily rate setting workflow before counter opening.
 * Managers review and approve rates before tellers can use them.
 */
class RateManagementService
{
    protected RateApiService $rateApiService;

    protected MathService $mathService;

    public function __construct(
        ?RateApiService $rateApiService = null,
        ?MathService $mathService = null
    ) {
        $this->rateApiService = $rateApiService ?? new RateApiService;
        $this->mathService = $mathService ?? new MathService;
    }

    /**
     * Fetch latest rates from API and store in exchange_rates table.
     * Called during daily setup before counter opening.
     *
     * @param  User|null  $initiatedBy  User who initiated the fetch
     * @return array{success: bool, message: string, rates: array}
     */
    public function fetchAndStoreRates(?User $initiatedBy = null): array
    {
        try {
            $rates = $this->rateApiService->fetchLatestRates();

            return [
                'success' => true,
                'message' => 'Rates fetched and stored successfully',
                'rates' => $rates,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch rates: '.$e->getMessage(),
                'rates' => [],
            ];
        }
    }

    /**
     * Get current rates from exchange_rates table for all currencies.
     *
     * @return Collection Current rates
     */
    public function getCurrentRates(): Collection
    {
        return ExchangeRate::all();
    }

    /**
     * Get rate for a specific currency.
     *
     * @param  string  $currencyCode  Currency code
     * @return ExchangeRate|null Rate model or null
     */
    public function getRateForCurrency(string $currencyCode): ?ExchangeRate
    {
        return ExchangeRate::where('currency_code', $currencyCode)->first();
    }

    /**
     * Manually override a rate.
     * Requires manager or admin role.
     *
     * @param  string  $currencyCode  Currency code
     * @param  string  $newBuyRate  New buy rate
     * @param  string  $newSellRate  New sell rate
     * @param  User  $approvedBy  User approving the change
     * @param  string|null  $reason  Reason for override
     * @return array{success: bool, message: string}
     */
    public function overrideRate(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        User $approvedBy,
        ?string $reason = null
    ): array {
        // Check permissions - only manager and above can override
        if (! $approvedBy->role->isManager() && ! $approvedBy->role->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions to override rates',
            ];
        }

        // Validate rates are positive
        if ($this->mathService->compare($newBuyRate, '0') <= 0 ||
            $this->mathService->compare($newSellRate, '0') <= 0) {
            return [
                'success' => false,
                'message' => 'Rates must be positive numbers',
            ];
        }

        // Validate sell rate is higher than buy rate (spread check)
        if ($this->mathService->compare($newSellRate, $newBuyRate) <= 0) {
            return [
                'success' => false,
                'message' => 'Sell rate must be higher than buy rate',
            ];
        }

        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (! $exchangeRate) {
            return [
                'success' => false,
                'message' => "No existing rate found for {$currencyCode}",
            ];
        }

        $oldBuyRate = $exchangeRate->rate_buy;
        $oldSellRate = $exchangeRate->rate_sell;

        $exchangeRate->update([
            'rate_buy' => $newBuyRate,
            'rate_sell' => $newSellRate,
            'source' => 'manual_override',
            'fetched_at' => now(),
        ]);

        // Log the override
        app(AuditService::class)->log(
            'rate_overridden',
            $approvedBy->id,
            'ExchangeRate',
            $exchangeRate->id,
            [
                'old_buy_rate' => $oldBuyRate,
                'old_sell_rate' => $oldSellRate,
                'new_buy_rate' => $newBuyRate,
                'new_sell_rate' => $newSellRate,
                'reason' => $reason,
            ],
            [
                'currency_code' => $currencyCode,
            ]
        );

        return [
            'success' => true,
            'message' => "Rate for {$currencyCode} overridden successfully",
            'old_buy_rate' => $oldBuyRate,
            'old_sell_rate' => $oldSellRate,
            'new_buy_rate' => $newBuyRate,
            'new_sell_rate' => $newSellRate,
        ];
    }

    /**
     * Validate a transaction rate against current market rate.
     *
     * @param  string  $submittedRate  Rate submitted by teller
     * @param  string  $currencyCode  Currency code
     * @param  string  $transactionType  'buy' or 'sell'
     * @return array{valid: bool, reason: string|null, deviation_percent: string|null}
     */
    public function validateTransactionRate(
        string $submittedRate,
        string $currencyCode,
        string $transactionType = 'buy'
    ): array {
        return $this->rateApiService->validateRateDeviation(
            $submittedRate,
            $currencyCode,
            $transactionType
        );
    }

    /**
     * Check if rates are available for a currency.
     *
     * @param  string  $currencyCode  Currency code
     * @return bool True if rate exists
     */
    public function hasRateForCurrency(string $currencyCode): bool
    {
        return ExchangeRate::where('currency_code', $currencyCode)->exists();
    }

    /**
     * Check if all required rates are set (for daily opening workflow).
     *
     * @param  array  $currencyCodes  Array of currency codes
     * @return array{all_set: bool, missing: array}
     */
    public function areAllRatesSet(array $currencyCodes): array
    {
        $missing = [];

        foreach ($currencyCodes as $code) {
            if (! $this->hasRateForCurrency($code)) {
                $missing[] = $code;
            }
        }

        return [
            'all_set' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Get rates summary for review before counter opening.
     *
     * @return array Summary of current rates
     */
    public function getRatesSummary(): array
    {
        $rates = $this->getCurrentRates();
        $summary = [];

        foreach ($rates as $rate) {
            $summary[] = [
                'currency_code' => $rate->currency_code,
                'rate_buy' => $rate->rate_buy,
                'rate_sell' => $rate->rate_sell,
                'spread' => $this->calculateSpread($rate->rate_buy, $rate->rate_sell),
                'fetched_at' => $rate->fetched_at?->toIso8601String(),
                'source' => $rate->source,
            ];
        }

        return $summary;
    }

    /**
     * Calculate the spread between buy and sell rates.
     *
     * @param  string  $buyRate  Buy rate
     * @param  string  $sellRate  Sell rate
     * @return string Spread as percentage
     */
    protected function calculateSpread(string $buyRate, string $sellRate): string
    {
        $mid = $this->mathService->divide(
            $this->mathService->add($buyRate, $sellRate),
            '2'
        );

        $halfSpread = $this->mathService->divide(
            $this->mathService->subtract($sellRate, $buyRate),
            '2'
        );

        if ($this->mathService->compare($mid, '0') > 0) {
            return bcadd(
                $this->mathService->multiply(
                    $this->mathService->divide($halfSpread, $mid),
                    '100'
                ),
                '0',
                2
            );
        }

        return '0';
    }
}
