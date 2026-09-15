<?php

namespace App\Services\Transaction;

use App\Enums\Permission;
use App\Exceptions\Domain\InvalidRateException;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\DTOs\RateOverrideResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RateManagementService implements RateManagementServiceInterface
{
    public function __construct(
        protected RateApiService $rateApiService,
        protected MathService $mathService,
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
        protected ?ThresholdService $thresholdService = null,
    ) {
        $this->thresholdService ??= app(ThresholdService::class);
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
        $query = ExchangeRate::query()->active();

        if ($branchId !== null) {
            // Branch rate card = company-wide rows (branch_id NULL) overlaid by
            // branch-specific overrides. Branch rows win per currency.
            $rates = $query
                ->where(fn ($q) => $q->forBranch($branchId)->orWhereNull('branch_id'))
                ->get();

            return $rates
                ->sortBy(fn (ExchangeRate $rate) => $rate->branch_id === $branchId ? 0 : 1)
                ->unique('currency_code')
                ->values();
        }

        return $query->get();
    }

    public function getRateForCurrency(string $currencyCode, ?int $branchId = null): ?ExchangeRate
    {
        $cacheKey = $this->rateCacheKey($currencyCode, $branchId);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($currencyCode, $branchId) {
            $query = ExchangeRate::where('currency_code', $currencyCode)->active();
            if ($branchId !== null) {
                // Branch override wins; fall back to the company-wide rate.
                return $query
                    ->where(fn ($q) => $q->forBranch($branchId)->orWhereNull('branch_id'))
                    ->orderByRaw('branch_id IS NULL')
                    ->first();
            }

            return $query->first();
        });
    }

    /**
     * Build the canonical per-currency rate cache key.
     *
     * Every writer (override, copy, API fetch) must invalidate this exact key
     * so readers never serve a stale rate after a rate change.
     */
    private function rateCacheKey(string $currencyCode, ?int $branchId = null): string
    {
        return 'rate:'.$currencyCode.($branchId !== null ? ':branch:'.$branchId : '');
    }

    /**
     * Forget the per-currency rate cache for a currency (optionally branch-scoped).
     */
    private function forgetRateCache(string $currencyCode, ?int $branchId = null): void
    {
        $this->cacheInvalidationService->forgetRate($currencyCode, $branchId);
    }

    public function getRateHistory(string $currencyCode, int $days, ?int $branchId = null): EloquentCollection
    {
        $query = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange(
                now()->subDays($days)->toDateString(),
                now()->toDateString()
            );

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('effective_date', 'desc')->get();
    }

    public function overrideRate(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        User $approvedBy,
        ?string $reason = null,
        ?int $branchId = null,
        ?string $effectiveDate = null
    ): RateOverrideResult {
        if (! $approvedBy->role->canPerform(Permission::AccessRates)) {
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

        $this->assertSpreadWithinLimits($newBuyRate, $newSellRate);

        $effectiveAt = $effectiveDate !== null
            ? Carbon::parse($effectiveDate)
            : now();

        return DB::transaction(function () use ($currencyCode, $newBuyRate, $newSellRate, $approvedBy, $reason, $branchId, $effectiveAt) {
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
                        'effective_date' => $effectiveAt,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $exchangeRate = $query->lockForUpdate()->firstOrFail();
                }

                // Invalidate cache
                $this->forgetRateCache($currencyCode, $branchId);

                // Rate creation is an override too — audit it like the update
                // path so every rate change is traceable.
                $this->auditService->log(
                    'rate_overridden',
                    $approvedBy->id,
                    'ExchangeRate',
                    $exchangeRate->id,
                    [
                        'old_buy_rate' => null,
                        'old_sell_rate' => null,
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
                'effective_date' => $effectiveAt,
            ]);

            // Invalidate cache
            $this->forgetRateCache($currencyCode, $branchId);

            $this->auditService->log(
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
        $query = ExchangeRate::where('currency_code', $currencyCode)->active();

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->forBranch($branchId)->orWhereNull('branch_id'));
        }

        return $query->exists();
    }

    public function areAllRatesSet(array $currencyCodes, ?int $branchId = null): array
    {
        // Single query instead of one exists() query per currency code.
        $query = ExchangeRate::whereIn('currency_code', $currencyCodes)->active();

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->forBranch($branchId)->orWhereNull('branch_id'));
        }

        $existing = $query->pluck('currency_code')->flip();
        $missing = collect($currencyCodes)
            ->reject(fn (string $code) => $existing->has($code))
            ->values()
            ->all();

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

    /**
     * Reject overrides whose buy/sell spread falls outside the configured
     * [min_spread, max_spread] band (thresholds.rates, expressed as a
     * fraction of mid — e.g. 0.005 = 0.5%).
     *
     * spread = (sell - buy) / (2 * mid) = (sell - buy) / (buy + sell)
     */
    protected function assertSpreadWithinLimits(string $buyRate, string $sellRate): void
    {
        $minSpread = (string) $this->thresholdService->get('rates', 'min_spread', 0.005);
        $maxSpread = (string) $this->thresholdService->get('rates', 'max_spread', 0.05);

        $denominator = $this->mathService->add($buyRate, $sellRate);

        if ($this->mathService->compare($denominator, '0') <= 0) {
            return;
        }

        $spread = $this->mathService->divide(
            $this->mathService->subtract($sellRate, $buyRate),
            $denominator
        );

        if ($this->mathService->compare($spread, $maxSpread) > 0) {
            throw new InvalidRateException(sprintf(
                'Spread %.2f%% exceeds the maximum allowed spread of %.2f%%.',
                (float) $this->mathService->multiply($spread, '100'),
                (float) $this->mathService->multiply($maxSpread, '100')
            ));
        }

        if ($this->mathService->compare($spread, $minSpread) < 0) {
            throw new InvalidRateException(sprintf(
                'Spread %.2f%% is below the minimum required spread of %.2f%%.',
                (float) $this->mathService->multiply($spread, '100'),
                (float) $this->mathService->multiply($minSpread, '100')
            ));
        }
    }

    public function copyPreviousRates(string $targetDate, ?int $branchId = null): array
    {
        // whereDate keeps the lookup correct regardless of whether the column
        // stores a pure date or a datetime (and across DB drivers).
        $historyQuery = ExchangeRateHistory::whereDate('effective_date', $targetDate);
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

                // History stores the MID rate only — writing it to both
                // rate_buy and rate_sell would flatten the spread to zero
                // (violating the sell > buy invariant and giving away the
                // margin). Re-derive the sides with the configured spread.
                $derived = $this->rateApiService->applySpread((string) $histRate->rate);

                $exchangeRate->update([
                    'rate_buy' => $derived['buy'],
                    'rate_sell' => $derived['sell'],
                    'source' => "copied_from_{$targetDate}",
                    'fetched_at' => now(),
                ]);

                // Invalidate per-currency cache so the copied rate is served immediately
                $this->forgetRateCache($histRate->currency_code, $branchId);

                $copied[] = [
                    'currency' => $histRate->currency_code,
                    'old_buy' => $oldBuy,
                    'old_sell' => $oldSell,
                    'new_buy' => $derived['buy'],
                    'new_sell' => $derived['sell'],
                    'mid' => $histRate->rate,
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
