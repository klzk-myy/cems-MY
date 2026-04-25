<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Collection;

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

    public function fetchAndStoreRates(?User $initiatedBy = null, ?int $branchId = null): array
    {
        try {
            $rates = $this->rateApiService->fetchLatestRates($branchId);

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

    public function getCurrentRates(?int $branchId = null): Collection
    {
        $query = ExchangeRate::query();

        if ($branchId !== null) {
            $query->forBranch($branchId);
        }

        return $query->get();
    }

    public function getRateForCurrency(string $currencyCode, ?int $branchId = null): ?ExchangeRate
    {
        $query = ExchangeRate::where('currency_code', $currencyCode);

        if ($branchId !== null) {
            $query->forBranch($branchId);
        }

        return $query->first();
    }

    public function overrideRate(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        User $approvedBy,
        ?string $reason = null,
        ?int $branchId = null
    ): array {
        if (! $approvedBy->role->isManager() && ! $approvedBy->role->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions to override rates',
            ];
        }

        if ($this->mathService->compare($newBuyRate, '0') <= 0 ||
            $this->mathService->compare($newSellRate, '0') <= 0) {
            return [
                'success' => false,
                'message' => 'Rates must be positive numbers',
            ];
        }

        if ($this->mathService->compare($newSellRate, $newBuyRate) <= 0) {
            return [
                'success' => false,
                'message' => 'Sell rate must be higher than buy rate',
            ];
        }

        $query = ExchangeRate::where('currency_code', $currencyCode);
        if ($branchId !== null) {
            $query->forBranch($branchId);
        }
        $exchangeRate = $query->first();

        if (! $exchangeRate) {
            $exchangeRate = ExchangeRate::create([
                'branch_id' => $branchId,
                'currency_code' => $currencyCode,
                'rate_buy' => $newBuyRate,
                'rate_sell' => $newSellRate,
                'source' => 'manual_override',
                'fetched_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => "Rate for {$currencyCode} created successfully",
                'old_buy_rate' => null,
                'old_sell_rate' => null,
                'new_buy_rate' => $newBuyRate,
                'new_sell_rate' => $newSellRate,
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
                'branch_id' => $branchId,
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

    public function validateTransactionRate(
        string $submittedRate,
        string $currencyCode,
        string $transactionType = 'buy',
        ?int $branchId = null
    ): array {
        return $this->rateApiService->validateRateDeviation(
            $submittedRate,
            $currencyCode,
            $transactionType,
            $branchId
        );
    }

    public function hasRateForCurrency(string $currencyCode, ?int $branchId = null): bool
    {
        $query = ExchangeRate::where('currency_code', $currencyCode);

        if ($branchId !== null) {
            $query->forBranch($branchId);
        }

        return $query->exists();
    }

    public function areAllRatesSet(array $currencyCodes, ?int $branchId = null): array
    {
        $missing = [];

        foreach ($currencyCodes as $code) {
            if (! $this->hasRateForCurrency($code, $branchId)) {
                $missing[] = $code;
            }
        }

        return [
            'all_set' => empty($missing),
            'missing' => $missing,
        ];
    }

    public function getRatesSummary(?int $branchId = null): array
    {
        $rates = $this->getCurrentRates($branchId);
        $summary = [];

        foreach ($rates as $rate) {
            $summary[] = [
                'currency_code' => $rate->currency_code,
                'rate_buy' => $rate->rate_buy,
                'rate_sell' => $rate->rate_sell,
                'spread' => $this->calculateSpread($rate->rate_buy, $rate->rate_sell),
                'fetched_at' => $rate->fetched_at?->toIso8601String(),
                'source' => $rate->source,
                'branch_id' => $rate->branch_id,
            ];
        }

        return $summary;
    }

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
