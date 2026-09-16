<?php

namespace App\Services\Transaction;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidRateException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\DTOs\RateOverrideResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
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
                ->forBranchOrCompany($branchId)
                ->get();

            return $rates
                ->sortBy(fn (ExchangeRate $rate) => $rate->branch_id === $branchId ? 0 : 1)
                ->unique('currency_code')
                ->values();
        }

        // No branch scope = the company rate card. Branch overrides belong to
        // their branch and must never appear here: returning every row leaked
        // other branches' cards and duplicated currency codes.
        return $query->whereNull('branch_id')->get();
    }

    public function getRateCard(string $currencyCode, ?int $branchId = null): ?ExchangeRate
    {
        $cacheKey = CacheKeys::rate($currencyCode, $branchId);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($currencyCode, $branchId) {
            $query = ExchangeRate::where('currency_code', $currencyCode)->active();
            if ($branchId !== null) {
                // Branch override wins; fall back to the company-wide rate.
                return $query
                    ->forBranchOrCompany($branchId)
                    ->orderByRaw('branch_id IS NULL')
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->first();
            }

            // Company-wide scope: prefer the company card, then the most
            // recently fetched row. An unordered first() could serve a stale
            // duplicate (or another branch's override) as today's rate.
            return $query
                ->orderByRaw('branch_id IS NULL')
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->first();
        });
    }

    /**
     * Forget the per-currency rate cache for a currency (optionally branch-scoped).
     */
    private function forgetRateCache(string $currencyCode, ?int $branchId = null): void
    {
        // A company-wide write changes what EVERY branch reader resolves to —
        // including branches with no card of their own, whose branch key holds
        // a cached fallback to the old company rate. Enumerate all branches,
        // not just branches that happen to have a card row.
        $branchScopes = $branchId !== null ? [$branchId] : $this->allBranchIds();

        $this->cacheInvalidationService->forgetRateScopes([$currencyCode], $branchScopes);
    }

    /**
     * Every branch id — a company-wide write can change what any branch reader
     * resolves to (its branch key may hold a cached fallback to the old company
     * rate), so invalidation must cover all scopes, not just carded branches.
     *
     * @return list<int>
     */
    private function allBranchIds(): array
    {
        return array_values(Branch::query()->pluck('id')->map(fn ($id) => (int) $id)->all());
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

        $spreadPercent = $this->calculateSpread($perUnitBuy, $perUnitSell);

        $effectiveAt = $effectiveDate !== null
            ? Carbon::parse($effectiveDate)
            : now();

        return DB::transaction(function () use ($currencyCode, $newBuyRate, $newSellRate, $approvedBy, $reason, $branchId, $effectiveAt, $convention, $spreadPercent) {
            $outcome = $this->persistOverride($currencyCode, $newBuyRate, $newSellRate, $branchId, $effectiveAt, $convention, $spreadPercent);

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
        QuoteConvention $convention,
        string $spreadPercent
    ): array {
        $query = ExchangeRate::where('currency_code', $currencyCode);
        if ($branchId !== null) {
            $query->forBranch($branchId);
        } else {
            // Match the company-wide card explicitly: without it, an
            // unordered first() could select (and overwrite) a branch row.
            $query->whereNull('branch_id');
        }
        $exchangeRate = $query->lockForUpdate()->first();

        $attributes = [
            'rate_buy' => $newBuyRate,
            'rate_sell' => $newSellRate,
            'rate_unit' => $convention->unit,
            'rate_inverse' => $convention->inverse,
            'source' => 'manual_override',
            'spread_applied' => $spreadPercent,
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
        ?int $branchId = null,
        ?UserRole $role = null,
    ): array {
        $result = $this->rateApiService->validateRateDeviation(
            $submittedRate,
            $currencyCode,
            $transactionType,
            $branchId
        );

        if ($role === null || ! ($result['valid'] ?? true)) {
            return $result;
        }

        $deviationPercent = $result['deviation_percent'] ?? null;

        // No deviation measured (no market card for the currency): nothing for
        // a role limit to act on.
        if ($deviationPercent === null) {
            return $result;
        }

        // BNM per-role override limits (thresholds.rates.override_limit_*,
        // in percentage points): a role may not book a rate further from
        // market than its own limit. Roles without a limit (admin, accountant,
        // compliance officer) are unlimited. Previously these limits existed
        // but were never consulted, leaving every booker on the loose global
        // band only.
        $limit = $role->rateOverrideLimit();

        if ($limit === null) {
            return $result;
        }

        if (bccomp($deviationPercent, (string) $limit, 8) <= 0) {
            return $result;
        }

        return [
            ...$result,
            'valid' => false,
            'reason' => sprintf(
                'Rate deviation %.2f%% exceeds the maximum %.2f%% allowed for your role (%s). Ask a manager to book this rate.',
                (float) $deviationPercent,
                $limit,
                $role->label()
            ),
            'role_limit_percent' => bcadd((string) $limit, '0', 2),
        ];
    }

    public function hasRateForCurrency(string $currencyCode, ?int $branchId = null): bool
    {
        $query = ExchangeRate::where('currency_code', $currencyCode)->active();

        if ($branchId !== null) {
            $query->forBranchOrCompany($branchId);
        }

        return $query->exists();
    }

    public function areAllRatesSet(array $currencyCodes, ?int $branchId = null): array
    {
        // Single query instead of one exists() query per currency code.
        $query = ExchangeRate::whereIn('currency_code', $currencyCodes)->active();

        if ($branchId !== null) {
            $query->forBranchOrCompany($branchId);
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
     * The configured buy/sell spread as a percentage string (e.g. '2.0000'),
     * matching the scale/units of the spread_applied column written on cards
     * derived from the configured spread.
     *
     * @return numeric-string
     */
    private function configuredSpreadPercent(): string
    {
        /** @var numeric-string $spread */
        $spread = $this->rateApiService->getSpread();

        return bcadd(bcmul($spread, '100', 4), '0', 4);
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

    /**
     * @return array{success: bool, message: string, copied_from_date?: string, rates: list<array{currency: string, old_buy: numeric-string, old_sell: numeric-string, new_buy: numeric-string, new_sell: numeric-string, rate_unit: string, mid: string}>}
     */
    public function copyPreviousRates(string $targetDate, ?int $branchId = null): array
    {
        // whereDate keeps the lookup correct regardless of whether the column
        // stores a pure date or a datetime (and across DB drivers).
        $historyQuery = ExchangeRateHistory::whereDate('effective_date', $targetDate);
        if ($branchId === null) {
            // A company-wide copy must read company-wide history only:
            // another branch's rows are that branch's card, not the company's,
            // and copying them contaminated the company rate card.
            $historyQuery->whereNull('branch_id');
        } else {
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
            ->when(
                $branchId !== null,
                fn ($q) => $q->forBranch($branchId),
                // Branch-scoped copies must never overwrite the company card,
                // and company-wide copies must never overwrite a branch card.
                // keyBy() over an unscoped result silently picked the last row,
                // which could be another branch's override.
                fn ($q) => $q->whereNull('branch_id')
            )
            ->get()
            ->keyBy('currency_code');

        $conventions = Currency::quoteConventions($currencyCodes->all());

        // The copy re-derives both sides with the configured spread, so that
        // spread is what the new card actually carries.
        $spread = $this->configuredSpreadPercent();

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
                    'spread_applied' => $spread,
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
