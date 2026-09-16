<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\InvalidRateException;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Support\ActorContext;
use App\ValueObjects\QuoteConvention;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RateApiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected MathService $mathService;

    protected CacheInvalidationService $cacheInvalidationService;

    /**
     * Rate thresholds resolved lazily via ThresholdService so constructing
     * the service stays free of DB I/O (keeps callers' query counts stable).
     *
     * @var array{spread: numeric-string, max_deviation_percent: numeric-string, precision: int, cache_duration: int}|null
     */
    private ?array $rateThresholds = null;

    public function __construct(
        MathService $mathService,
        CacheInvalidationService $cacheInvalidationService,
        protected ThresholdService $thresholdService,
    ) {
        $this->mathService = $mathService;
        $this->cacheInvalidationService = $cacheInvalidationService;
        $this->apiKey = config('services.exchange_rate_api.key') ?? '';
        $this->baseUrl = config('services.exchange_rate_api.base_url', 'https://api.exchangerate-api.com/v4');
    }

    /**
     * @return array{spread: numeric-string, max_deviation_percent: numeric-string, precision: int, cache_duration: int}
     */
    private function rateThresholds(): array
    {
        /** @var numeric-string $spread */
        $spread = (string) $this->thresholdService->get('rates', 'spread', 0.02);
        /** @var numeric-string $maxDeviation */
        $maxDeviation = (string) $this->thresholdService->get('rates', 'max_deviation_percent', 0.05);

        return $this->rateThresholds ??= [
            'spread' => $spread,
            'max_deviation_percent' => $maxDeviation,
            'precision' => (int) $this->thresholdService->get('rates', 'precision', 4),
            'cache_duration' => (int) $this->thresholdService->get('rates', 'cache_duration', 60),
        ];
    }

    public function fetchLatestRates(?int $branchId = null): array
    {
        if (empty($this->apiKey)) {
            throw new InvalidRateException('EXCHANGE_RATE_API_KEY is not configured. Set it in .env');
        }

        $cacheKey = $branchId ? "exchange_rates_branch_{$branchId}" : 'exchange_rates';

        return Cache::remember($cacheKey, $this->rateThresholds()['cache_duration'], function () use ($branchId) {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->retry(3, 100)
                ->get("{$this->baseUrl}/latest/MYR");

            if (! $response->successful()) {
                throw new InvalidRateException('Failed to fetch exchange rates: '.$response->body());
            }

            $data = $response->json();

            if (! isset($data['rates'])) {
                throw new InvalidRateException('Invalid API response format');
            }

            $processed = $this->processRates($data['rates'], $data['time_last_updated'] ?? time());

            $this->storeRatesToTable($processed, $branchId);
            $this->logRatesToHistory($processed, $branchId);
            $this->invalidatePerCurrencyCache($processed, $branchId);

            return $processed;
        });
    }

    /**
     * Read-only rate lookup for callers that need rates outside the
     * configured api_rates.currencies set (e.g. the setup wizard's custom
     * "other" currencies). Nothing is stored, logged, or cached.
     *
     * The upstream API quotes CCY-per-MYR (rates[CCY] = units of CCY per 1
     * MYR); the stored convention is MYR per 1 CCY, so each mid is inverted
     * before the configured spread is applied. Values are returned at 8
     * decimals — the exchange_rates column precision — since low-value
     * currencies (IDR, VND) are meaningless at the default 4.
     *
     * @param  list<string>  $codes
     * @return array<string, array{buy: string, sell: string, mid: string}>
     */
    public function previewRates(array $codes): array
    {
        // With no API key configured, fall back to the provider's open
        // endpoint so the lookup still works in keyless environments.
        $url = $this->apiKey !== ''
            ? "{$this->baseUrl}/latest/MYR"
            : 'https://open.er-api.com/v6/latest/MYR';

        $response = Http::timeout(15)->connectTimeout(5)->get($url);

        $data = $response->json();

        if (! $response->successful() || ! isset($data['rates']) || ! is_array($data['rates'])) {
            throw new InvalidRateException('Failed to fetch exchange rates.');
        }

        $market = $data['rates'];
        $spread = $this->rateThresholds()['spread'];

        $rates = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '' || ! isset($market[$code]) || ! is_numeric($market[$code])) {
                continue;
            }

            // Keep 8 decimals through the spread multiplication — the default
            // service scale truncates low-value currencies (IDR, VND) to zero
            // before the final 8-decimal column rounding.
            $mid = $this->mathService->divide('1', (string) $market[$code], 8);

            $rates[$code] = [
                'buy' => bcadd($this->mathService->multiply($mid, $this->mathService->subtract('1', $spread), 8), '0', 8),
                'sell' => bcadd($this->mathService->multiply($mid, $this->mathService->add('1', $spread), 8), '0', 8),
                'mid' => bcadd($mid, '0', 8),
            ];
        }

        return $rates;
    }

    /**
     * Forget the per-currency rate cache entries (used by RateManagementService::getRateForCurrency)
     * so newly fetched rates are served immediately instead of the stale 5-minute cache.
     */
    protected function invalidatePerCurrencyCache(array $processed, ?int $branchId = null): void
    {
        $this->cacheInvalidationService->forgetAllRates(array_keys($processed), $branchId);
    }

    /**
     * The upstream API quotes CCY-per-MYR (rates[CCY] = units of CCY per 1
     * MYR); everything downstream works in per-unit MYR, so each mid is
     * inverted at scale 8 before the configured spread is applied —
     * low-value currencies (IDR, VND) would collapse at the default scale.
     */
    protected function processRates(array $rates, $timestamp): array
    {
        $processed = [];
        $currencies = config('cems.api_rates.currencies', ['USD', 'EUR', 'GBP', 'SGD', 'AUD', 'CAD', 'CHF', 'JPY']);

        foreach ($currencies as $currency) {
            if (isset($rates[$currency])) {
                $mid = $this->mathService->divide('1', (string) $rates[$currency], 8);
                $processed[$currency] = [
                    ...$this->applySpread($mid, 8),
                    'timestamp' => $timestamp,
                ];
            }
        }

        return $processed;
    }

    /**
     * Derive buy/sell rates from a mid rate using the configured spread:
     * buy = mid * (1 - spread), sell = mid * (1 + spread).
     * e.g., 2% spread: buy is 2% below mid, sell is 2% above mid.
     *
     * @return array{buy: string, sell: string, mid: string}
     */
    public function applySpread(string $midRate, ?int $precision = null): array
    {
        $spread = $this->rateThresholds()['spread'];
        $round = function (string $value) use ($precision): string {
            /** @var numeric-string $value */
            return $precision === null
                ? $this->roundRate($value)
                : bcadd($value, '0', $precision);
        };

        return [
            'buy' => $round($this->mathService->multiply($midRate, $this->mathService->subtract('1', $spread))),
            'sell' => $round($this->mathService->multiply($midRate, $this->mathService->add('1', $spread))),
            'mid' => $round($midRate),
        ];
    }

    /**
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    protected function roundRate(string $rate): string
    {
        if (! is_numeric($rate)) {
            throw new \InvalidArgumentException('Exchange rate must be a numeric string.');
        }

        return bcadd($rate, '0', $this->rateThresholds()['precision']);
    }

    protected function storeRatesToTable(array $rates, ?int $branchId = null): void
    {
        $now = now();

        // Processed rates are per-unit; exchange_rates stores the unit-quoted
        // convention (rate per currencies.rate_unit foreign units — or foreign
        // units per rate_unit MYR for inverse currencies), so each value is
        // re-quoted by the currency's configured convention.
        $conventions = Currency::quoteConventions(array_keys($rates));

        ExchangeRate::upsert(
            collect($rates)->map(function ($rateData, $currencyCode) use ($conventions, $branchId, $now) {
                $convention = $conventions[$currencyCode] ?? new QuoteConvention;

                return [
                    'currency_code' => $currencyCode,
                    'branch_id' => $branchId,
                    'rate_buy' => $convention->fromPerUnit($rateData['buy']),
                    'rate_sell' => $convention->fromPerUnit($rateData['sell']),
                    'rate_unit' => $convention->unit,
                    'rate_inverse' => $convention->inverse,
                    'source' => 'api',
                    'fetched_at' => $now,
                ];
            })->values()->all(),
            ['currency_code', 'branch_id'],
            ['rate_buy', 'rate_sell', 'rate_unit', 'rate_inverse', 'source', 'fetched_at']
        );
    }

    protected function logRatesToHistory(array $rates, ?int $branchId = null): void
    {
        $today = now()->toDateString();
        $userId = ActorContext::capture()->userId ?? config('cems.system_user_id', 1);

        $existing = ExchangeRateHistory::where('branch_id', $branchId)
            ->whereIn('currency_code', array_keys($rates))
            ->whereDate('effective_date', $today)
            ->pluck('currency_code')
            ->flip();

        $conventions = Currency::quoteConventions(array_keys($rates));

        $rows = collect($rates)
            ->reject(fn ($_, $currencyCode) => $existing->has($currencyCode))
            ->map(function ($rateData, $currencyCode) use ($conventions, $branchId, $today, $userId) {
                $convention = $conventions[$currencyCode] ?? new QuoteConvention;
                $buy = $convention->fromPerUnit($rateData['buy']);
                $sell = $convention->fromPerUnit($rateData['sell']);
                $side = $convention->inverse ? Currency::baseCurrency() : 'units';

                return [
                    'currency_code' => $currencyCode,
                    'branch_id' => $branchId,
                    'rate' => $convention->fromPerUnit($rateData['mid']),
                    'rate_unit' => $convention->unit,
                    'rate_inverse' => $convention->inverse,
                    'effective_date' => $today,
                    'created_by' => $userId,
                    'notes' => "API fetch - Buy: {$buy}, Sell: {$sell} per {$convention->unit} {$side}".($branchId ? " (Branch: {$branchId})" : ''),
                ];
            })->values()->all();

        if (! empty($rows)) {
            ExchangeRateHistory::insert($rows);
        }
    }

    public function getRateForCurrency(string $currency, ?int $branchId = null): ?array
    {
        $rates = $this->fetchLatestRates($branchId);

        return $rates[$currency] ?? null;
    }

    /**
     * @return numeric-string|null
     */
    public function getCurrentRate(string $currencyCode, string $type = 'mid', ?int $branchId = null): ?string
    {
        $query = ExchangeRate::where('currency_code', $currencyCode);
        if ($branchId !== null) {
            // Branch override wins; fall back to the company-wide rate so the
            // deviation guard still applies at branches without their own card.
            $query->where(fn ($q) => $q->forBranch($branchId)->orWhereNull('branch_id'))
                ->orderByRaw('branch_id IS NULL');
        }
        $exchangeRate = $query->first();

        if (! $exchangeRate) {
            return null;
        }

        // exchange_rates stores unit-quoted values (MYR per rate_unit foreign
        // units — or foreign units per rate_unit MYR for inverse rows);
        // callers compare and convert in per-unit terms, so normalize by the
        // row's own quote convention here.
        $toPerUnit = fn (string $quoted) => $exchangeRate->perUnitRate($quoted);

        return match ($type) {
            'buy' => $toPerUnit($exchangeRate->rate_buy),
            'sell' => $toPerUnit($exchangeRate->rate_sell),
            // Keep the per-unit 8-decimal convention: rounding the mid to
            // the display precision would collapse low-value currencies
            // (e.g. IDR 0.000235 → 0.0002).
            'mid' => bcadd(
                $this->mathService->divide(
                    bcadd(
                        $toPerUnit($exchangeRate->rate_buy),
                        $toPerUnit($exchangeRate->rate_sell),
                        8
                    ),
                    '2',
                    8
                ),
                '0',
                8
            ),
            default => $toPerUnit($exchangeRate->rate_buy),
        };
    }

    public function validateRateDeviation(
        string $submittedRate,
        string $currencyCode,
        string $type = 'buy',
        ?int $branchId = null
    ): array {
        // The submitted rate arrives in the currency's configured quote
        // convention (what the teller/UI displays): direct = MYR per rate_unit
        // foreign units, inverse = foreign units per rate_unit MYR. Normalize
        // to per-unit MYR so it compares against the market rate on equal terms.
        $convention = QuoteConvention::forCode($currencyCode);
        $submittedPerUnit = $convention->toPerUnit($submittedRate);

        $marketRate = $this->getCurrentRate($currencyCode, $type, $branchId);

        if ($marketRate === null) {
            return [
                'valid' => true,
                'reason' => null,
                'deviation_percent' => null,
                'max_allowed' => $this->rateThresholds()['max_deviation_percent'],
            ];
        }

        // Scale-8 arithmetic: per-unit market rates are tiny (IDR 0.000235),
        // so the default scale-4 subtract/divide would collapse the deviation
        // to zero and silently pass out-of-band rates.
        $deviation = $this->mathService->abs(bcsub($submittedPerUnit, $marketRate, 8));

        // max_deviation_percent is stored as a fraction (0.05 = 5%) per
        // thresholds.rates metadata — compare the deviation fraction directly.
        $deviationFraction = bcdiv($deviation, $marketRate, 8);
        $deviationPercent = bcmul($deviationFraction, '100', 4);

        $maxAllowed = $this->rateThresholds()['max_deviation_percent'];

        $isValid = bccomp($deviationFraction, (string) $maxAllowed, 8) <= 0;

        $maxAllowedPercent = bcmul((string) $maxAllowed, '100', 4);

        return [
            'valid' => $isValid,
            'reason' => $isValid ? null : "Rate deviation {$deviationPercent}% exceeds maximum allowed {$maxAllowedPercent}%",
            'deviation_percent' => $this->roundRate($deviationPercent),
            'max_allowed' => $maxAllowed,
            'market_rate' => $marketRate,
            'submitted_rate' => $submittedRate,
            'submitted_rate_per_unit' => $submittedPerUnit,
            'submitted_rate_unit' => (string) $convention->unit,
            'submitted_rate_inverse' => $convention->inverse,
        ];
    }

    public function clearCache(?int $branchId = null): void
    {
        $this->cacheInvalidationService->forgetExchangeRates($branchId);
    }

    public function getRateTrend(string $currencyCode, int $days = 30, ?int $branchId = null): array
    {
        $endDate = now()->toDateString();
        $startDate = now()->subDays($days)->toDateString();

        $query = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange($startDate, $endDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $histories = $query->orderBy('effective_date', 'asc')->get();

        if ($histories->isEmpty()) {
            return [
                'currency' => $currencyCode,
                'days' => $days,
                'data' => [],
                'trend' => null,
            ];
        }

        $data = $histories->map(function ($history) {
            return [
                'date' => $history->effective_date->format('Y-m-d'),
                'rate' => $history->rate,
                'rate_unit' => (string) $history->rate_unit,
                'rate_inverse' => (bool) $history->rate_inverse,
            ];
        })->toArray();

        $firstRate = $histories->first()->rate;
        $lastRate = $histories->last()->rate;
        $firstRateStr = (string) $firstRate;
        $lastRateStr = (string) $lastRate;

        $change = $this->mathService->subtract($lastRateStr, $firstRateStr);

        if ($this->mathService->compare($firstRateStr, '0') > 0) {
            $percentChangeRaw = $this->mathService->divide($change, $firstRateStr);
            $percentChange = $this->mathService->add($this->mathService->multiply($percentChangeRaw, '100'), '0');
        } else {
            $percentChange = '0';
        }

        return [
            'currency' => $currencyCode,
            'days' => $days,
            'data' => $data,
            'trend' => [
                'start_rate' => $firstRate,
                'end_rate' => $lastRate,
                'change' => $change,
                'percent_change' => $percentChange,
                'direction' => $this->mathService->compare($change, '0') >= 0 ? 'up' : 'down',
            ],
        ];
    }

    public function getSpread(): string
    {
        return $this->rateThresholds()['spread'];
    }

    public function getMaxDeviationPercent(): string
    {
        return $this->rateThresholds()['max_deviation_percent'];
    }

    public function getPrecision(): int
    {
        return $this->rateThresholds()['precision'];
    }
}
