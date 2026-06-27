<?php

namespace App\Services\Transaction;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\DTOs\RateOverrideResult;
use App\Services\System\MathService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RateManagementService implements RateManagementServiceInterface
{
    public function __construct(
        protected RateApiService $rateApiService,
        protected MathService $mathService
    ) {}

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
        $cacheKey = 'rate:'.$currencyCode.($branchId ? ':'.$branchId : '');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($currencyCode, $branchId) {
            $query = ExchangeRate::where('currency_code', $currencyCode);
            if ($branchId !== null) {
                $query->forBranch($branchId);
            }

            return $query->first();
        });
    }

    public function overrideRate(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        User $approvedBy,
        ?string $reason = null,
        ?int $branchId = null
    ): RateOverrideResult {
        if (! $approvedBy->role->isManager() && ! $approvedBy->role->isAdmin()) {
            return new RateOverrideResult(
                success: false,
                message: 'Insufficient permissions to override rates',
            );
        }

        if ($this->mathService->compare($newBuyRate, '0') <= 0 ||
            $this->mathService->compare($newSellRate, '0') <= 0) {
            return new RateOverrideResult(
                success: false,
                message: 'Rates must be positive numbers',
            );
        }

        if ($this->mathService->compare($newSellRate, $newBuyRate) <= 0) {
            return new RateOverrideResult(
                success: false,
                message: 'Sell rate must be higher than buy rate',
            );
        }

        return DB::transaction(function () use ($currencyCode, $newBuyRate, $newSellRate, $approvedBy, $reason, $branchId) {
            $query = ExchangeRate::where('currency_code', $currencyCode);
            if ($branchId !== null) {
                $query->forBranch($branchId);
            }
            $exchangeRate = $query->lockForUpdate()->first();

            if (! $exchangeRate) {
                try {
                    $exchangeRate = ExchangeRate::create([
                        'branch_id' => $branchId,
                        'currency_code' => $currencyCode,
                        'rate_buy' => $newBuyRate,
                        'rate_sell' => $newSellRate,
                        'source' => 'manual_override',
                        'fetched_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $exchangeRate = $query->lockForUpdate()->firstOrFail();
                }

                // Invalidate cache
                $cacheKey = 'rate:'.$currencyCode.($branchId ? ':'.$branchId : '');
                Cache::forget($cacheKey);

                return new RateOverrideResult(
                    success: true,
                    message: "Rate for {$currencyCode} created successfully",
                    previousRate: null,
                    newRate: $newBuyRate,
                );
            }

            $oldBuyRate = $exchangeRate->rate_buy;
            $oldSellRate = $exchangeRate->rate_sell;

            $exchangeRate->update([
                'rate_buy' => $newBuyRate,
                'rate_sell' => $newSellRate,
                'source' => 'manual_override',
                'fetched_at' => now(),
            ]);

            // Invalidate cache
            $cacheKey = 'rate:'.$currencyCode.($branchId ? ':'.$branchId : '');
            Cache::forget($cacheKey);

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

            return new RateOverrideResult(
                success: true,
                message: "Rate for {$currencyCode} overridden successfully",
                previousRate: $oldBuyRate,
                newRate: $newBuyRate,
            );
        });
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
        // Standardized formula: spread percentage = (sell - buy) / (2 * mid) * 100
        // This matches the RateApiService spread application where:
        // buy = mid * (1 - spread) and sell = mid * (1 + spread)
        // So sell - buy = 2 * spread * mid, thus spread = (sell - buy) / (2 * mid)
        $mid = $this->mathService->divide(
            $this->mathService->add($buyRate, $sellRate),
            '2'
        );

        if ($this->mathService->compare($mid, '0') > 0) {
            // Divide by 2*mid to get the spread fraction, then multiply by 100 for percentage
            $spread = $this->mathService->divide(
                $this->mathService->subtract($sellRate, $buyRate),
                $this->mathService->multiply($mid, '2')
            );

            return $this->mathService->add(
                $this->mathService->multiply($spread, '100'),
                '0'
            );
        }

        return '0';
    }

    public function copyPreviousRates(string $targetDate, ?int $branchId = null): array
    {
        $historyQuery = ExchangeRateHistory::where('effective_date', $targetDate);
        if ($branchId !== null) {
            $historyQuery->where('branch_id', $branchId);
        }
        $historicalRates = $historyQuery->get();

        if ($historicalRates->isEmpty()) {
            return [
                'success' => false,
                'message' => "No rates found for date {$targetDate}",
                'rates' => [],
            ];
        }

        $currencyCodes = $historicalRates->pluck('currency_code')->unique();

        $exchangeRates = ExchangeRate::whereIn('currency_code', $currencyCodes)
            ->when($branchId !== null, fn ($q) => $q->forBranch($branchId))
            ->get()
            ->keyBy('currency_code');

        $copied = [];
        foreach ($historicalRates as $histRate) {
            $exchangeRate = $exchangeRates->get($histRate->currency_code);

            if ($exchangeRate) {
                $oldBuy = $exchangeRate->rate_buy;
                $oldSell = $exchangeRate->rate_sell;

                $exchangeRate->update([
                    'rate_buy' => $histRate->rate,
                    'rate_sell' => $histRate->rate,
                    'source' => "copied_from_{$targetDate}",
                    'fetched_at' => now(),
                ]);

                $copied[] = [
                    'currency' => $histRate->currency_code,
                    'old_buy' => $oldBuy,
                    'old_sell' => $oldSell,
                    'new_rate' => $histRate->rate,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Rates copied successfully',
            'copied_from_date' => $targetDate,
            'rates' => $copied,
        ];
    }
}
