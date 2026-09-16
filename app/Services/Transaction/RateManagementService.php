<?php

namespace App\Services\Transaction;

use App\Enums\Permission;
use App\Exceptions\Domain\InvalidRateException;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\DTOs\RateOverrideResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\ValueObjects\QuoteConvention;
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
        protected ThresholdService $thresholdService,
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

        // The submitted buy/sell are quoted in the currency's convention:
        // direct = MYR per rate_unit foreign units (e.g. RM 235 per 1,000,000
        // IDR); inverse = foreign units per rate_unit MYR (e.g. RM 1 = 4,255
        // IDR). Validation runs on normalized per-unit MYR values where the
        // sell > buy invariant holds for both directions.
        $convention = QuoteConvention::for(Currency::find($currencyCode));

        if ($convention->unit < 1) {
            return new RateOverrideResult(
                success: false,
                message: 'Rate unit must be a positive integer',
            );
        }

        if ($this->mathService->compare($newBuyRate, '0') <= 0 ||
            $this->mathService->compare($newSellRate, '0') <= 0) {
            return new RateOverrideResult(
                success: false,
                message: 'Rates must be positive numbers',
            );
        }

        $perUnitBuy = $convention->toPerUnit($newBuyRate);
        $perUnitSell = $convention->toPerUnit($newSellRate);

        // Scale-8 compare: per-unit values can differ only beyond 4 decimals
        // (IDR 0.000235 vs 0.000245), which the default scale would see as equal.
        if (bccomp($perUnitSell, $perUnitBuy, 8) <= 0) {
            return new RateOverrideResult(
                success: false,
                message: $convention->inverse
                    ? 'Buy rate must be higher than sell rate for inverse quotes'
                    : 'Sell rate must be higher than buy rate',
            );
        }

        $this->assertSpreadWithinLimits($perUnitBuy, $perUnitSell);

        $effectiveAt = $effectiveDate !== null
            ? Carbon::parse($effectiveDate)
            : now();

        return DB::transaction(function () use ($currencyCode, $newBuyRate, $newSellRate, $approvedBy, $reason, $branchId, $effectiveAt, $convention) {
            $outcome = $this->persistOverride($currencyCode, $newBuyRate, $newSellRate, $branchId, $effectiveAt, $convention);

            // Invalidate cache
            $this->forgetRateCache($currencyCode, $branchId);

            $this->auditRateOverride($outcome['rate'], $approvedBy, $newBuyRate, $newSellRate, $outcome['old_buy'], $outcome['old_sell'], $reason, $currencyCode, $branchId);

            return new RateOverrideResult(
                success: true,
                message: $outcome['created']
                    ? "Rate for {$currencyCode} created successfully"
                    : "Rate for {$currencyCode} overridden successfully",
                previousRate: $outcome['old_buy'],
                newRate: $newBuyRate,
            );
        });
    }

    /**
     * Create or update the exchange rate row under a row lock.
     *
     * On a unique-constraint race the concurrently-inserted row is re-fetched
     * and reported as a create (null old rates) — the existing row's values
     * are left untouched, matching the pre-extraction behavior.
     *
     * @return array{rate: ExchangeRate, old_buy: ?string, old_sell: ?string, created: bool}
     */
    private function persistOverride(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        ?int $branchId,
        Carbon $effectiveAt,
        QuoteConvention $convention
    ): array {
        $query = ExchangeRate::where('currency_code', $currencyCode);
        if ($branchId !== null) {
            $query->forBranch($branchId);
        }
        $exchangeRate = $query->lockForUpdate()->first();

        $attributes = [
            'rate_buy' => $newBuyRate,
            'rate_sell' => $newSellRate,
            'rate_unit' => $convention->unit,
            'rate_inverse' => $convention->inverse,
            'source' => 'manual_override',
            'fetched_at' => now(),
            'effective_date' => $effectiveAt,
        ];

        if (! $exchangeRate) {
            try {
                $exchangeRate = ExchangeRate::create($attributes + [
                    'branch_id' => $branchId,
                    'currency_code' => $currencyCode,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $exchangeRate = $query->lockForUpdate()->firstOrFail();
            }

            return [
                'rate' => $exchangeRate,
                'old_buy' => null,
                'old_sell' => null,
                'created' => true,
            ];
        }

        $oldBuyRate = $exchangeRate->rate_buy;
        $oldSellRate = $exchangeRate->rate_sell;

        $exchangeRate->update($attributes);

        return [
            'rate' => $exchangeRate,
            'old_buy' => $oldBuyRate,
            'old_sell' => $oldSellRate,
            'created' => false,
        ];
    }

    /**
     * Audit a rate create or override with an identical payload shape so
     * every rate change is traceable (old_* null on create).
     */
    private function auditRateOverride(
        ExchangeRate $exchangeRate,
        User $approvedBy,
        string $newBuyRate,
        string $newSellRate,
        ?string $oldBuyRate,
        ?string $oldSellRate,
        ?string $reason,
        string $currencyCode,
        ?int $branchId
    ): void {
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

        $conventions = Currency::quoteConventions($rates->pluck('currency_code')->unique()->all());

        foreach ($rates as $rate) {
            $currencyConvention = $conventions[$rate->currency_code] ?? new QuoteConvention;
            $summary[] = [
                'currency_code' => $rate->currency_code,
                'rate_buy' => $rate->rate_buy,
                'rate_sell' => $rate->rate_sell,
                'rate_unit' => (string) $rate->rate_unit,
                'rate_inverse' => (bool) $rate->rate_inverse,
                'currency_rate_unit' => (string) $currencyConvention->unit,
                'currency_rate_inverse' => $currencyConvention->inverse,
                'currency_convention' => $currencyConvention,
                // Spread is computed on normalized per-unit values so inverse
                // rows (where quoted sell < buy) report the same positive
                // spread as direct rows.
                'spread' => $this->calculateSpread(
                    $rate->perUnitRate((string) $rate->rate_buy),
                    $rate->perUnitRate((string) $rate->rate_sell)
                ),
                'fetched_at' => $rate->fetched_at?->toIso8601String(),
                'source' => $rate->source,
                'branch_id' => $rate->branch_id,
            ];
        }

        return $summary;
    }

    /**
     * @param  numeric-string  $buyRate
     * @param  numeric-string  $sellRate
     * @return numeric-string
     */
    protected function calculateSpread(string $buyRate, string $sellRate): string
    {
        // Standardized formula: spread percentage = (sell - buy) / (2 * mid) * 100
        // This matches the RateApiService spread application where:
        // buy = mid * (1 - spread) and sell = mid * (1 + spread)
        // So sell - buy = 2 * spread * mid, thus spread = (sell - buy) / (2 * mid)
        // Scale-8 arithmetic keeps low-value per-unit rates (e.g. IDR
        // 0.000235) from collapsing to zero at the default scale of 4.
        $mid = bcdiv(bcadd($buyRate, $sellRate, 8), '2', 8);

        if (bccomp($mid, '0', 8) > 0) {
            $spread = bcdiv(bcsub($sellRate, $buyRate, 8), bcmul($mid, '2', 8), 8);

            return bcadd(bcmul($spread, '100', 8), '0', 4);
        }

        return '0';
    }

    /**
     * Reject overrides whose buy/sell spread falls outside the configured
     * [min_spread, max_spread] band (thresholds.rates, expressed as a
     * fraction of mid — e.g. 0.005 = 0.5%).
     *
     * spread = (sell - buy) / (2 * mid) = (sell - buy) / (buy + sell)
     *
     * @param  numeric-string  $buyRate
     * @param  numeric-string  $sellRate
     */
    protected function assertSpreadWithinLimits(string $buyRate, string $sellRate): void
    {
        /** @var numeric-string $minSpread */
        $minSpread = (string) $this->thresholdService->get('rates', 'min_spread', 0.005);
        /** @var numeric-string $maxSpread */
        $maxSpread = (string) $this->thresholdService->get('rates', 'max_spread', 0.05);

        // Scale-8 arithmetic keeps low-value per-unit rates (e.g. IDR
        // 0.000235) from collapsing to zero at the default scale of 4.
        $denominator = bcadd($buyRate, $sellRate, 8);

        if (bccomp($denominator, '0', 8) <= 0) {
            return;
        }

        $spread = bcdiv(bcsub($sellRate, $buyRate, 8), $denominator, 8);

        if (bccomp($spread, $maxSpread, 8) > 0) {
            throw new InvalidRateException(sprintf(
                'Spread %.2f%% exceeds the maximum allowed spread of %.2f%%.',
                (float) $this->mathService->multiply($spread, '100'),
                (float) $this->mathService->multiply($maxSpread, '100')
            ));
        }

        if (bccomp($spread, $minSpread, 8) < 0) {
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

        $conventions = Currency::quoteConventions($currencyCodes->all());

        $copied = [];
        foreach ($historicalRates as $histRate) {
            $exchangeRate = $exchangeRates->get($histRate->currency_code);

            if ($exchangeRate) {
                $oldBuy = $exchangeRate->rate_buy;
                $oldSell = $exchangeRate->rate_sell;

                // History stores the MID rate only — writing it to both
                // rate_buy and rate_sell would flatten the spread to zero
                // (violating the sell > buy invariant and giving away the
                // margin). Normalize the mid to per-unit (bridging the
                // history row's own unit/direction), re-derive the sides with
                // the configured spread at per-unit precision, then re-quote
                // into the currency's currently configured convention.
                $perUnitMid = $histRate->perUnitRate((string) $histRate->rate);
                $derived = $this->rateApiService->applySpread($perUnitMid, 8);

                $targetConvention = $conventions[$histRate->currency_code] ?? new QuoteConvention;

                $newBuy = $targetConvention->fromPerUnit($derived['buy']);
                $newSell = $targetConvention->fromPerUnit($derived['sell']);

                $exchangeRate->update([
                    'rate_buy' => $newBuy,
                    'rate_sell' => $newSell,
                    'rate_unit' => $targetConvention->unit,
                    'rate_inverse' => $targetConvention->inverse,
                    'source' => "copied_from_{$targetDate}",
                    'fetched_at' => now(),
                ]);

                // Invalidate per-currency cache so the copied rate is served immediately
                $this->forgetRateCache($histRate->currency_code, $branchId);

                $copied[] = [
                    'currency' => $histRate->currency_code,
                    'old_buy' => $oldBuy,
                    'old_sell' => $oldSell,
                    'new_buy' => $newBuy,
                    'new_sell' => $newSell,
                    'rate_unit' => (string) $targetConvention->unit,
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
