<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\InvalidRateException;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
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
     * @var array{spread: string, max_deviation_percent: string, precision: int, cache_duration: int}|null
     */
    private ?array $rateThresholds = null;

    public function __construct(
        ?MathService $mathService = null,
        ?CacheInvalidationService $cacheInvalidationService = null,
        protected ?ThresholdService $thresholdService = null,
    ) {
        $this->mathService = $mathService ?? new MathService;
        $this->cacheInvalidationService = $cacheInvalidationService ?? new CacheInvalidationService;
        $this->thresholdService ??= app(ThresholdService::class);
        $this->apiKey = config('services.exchange_rate_api.key') ?? '';
        $this->baseUrl = config('services.exchange_rate_api.base_url', 'https://api.exchangerate-api.com/v4');
    }

    /**
     * @return array{spread: string, max_deviation_percent: string, precision: int, cache_duration: int}
     */
    private function rateThresholds(): array
    {
        return $this->rateThresholds ??= [
            'spread' => (string) $this->thresholdService->get('rates', 'spread', 0.02),
            'max_deviation_percent' => (string) $this->thresholdService->get('rates', 'max_deviation_percent', 0.05),
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
     * Forget the per-currency rate cache entries (used by RateManagementService::getRateForCurrency)
     * so newly fetched rates are served immediately instead of the stale 5-minute cache.
     */
    protected function invalidatePerCurrencyCache(array $processed, ?int $branchId = null): void
    {
        $this->cacheInvalidationService->forgetAllRates(array_keys($processed), $branchId);
    }

    protected function processRates(array $rates, $timestamp): array
    {
        $processed = [];
        $currencies = config('cems.api_rates.currencies', ['USD', 'EUR', 'GBP', 'SGD', 'AUD', 'CAD', 'CHF', 'JPY']);

        foreach ($currencies as $currency) {
            if (isset($rates[$currency])) {
                $processed[$currency] = [
                    ...$this->applySpread((string) $rates[$currency]),
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
    public function applySpread(string $midRate): array
    {
        $spread = $this->rateThresholds()['spread'];

        return [
            'buy' => $this->roundRate($this->mathService->multiply($midRate, $this->mathService->subtract('1', $spread))),
            'sell' => $this->roundRate($this->mathService->multiply($midRate, $this->mathService->add('1', $spread))),
            'mid' => $this->roundRate($midRate),
        ];
    }

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

        ExchangeRate::upsert(
            collect($rates)->map(fn ($rateData, $currencyCode) => [
                'currency_code' => $currencyCode,
                'branch_id' => $branchId,
                'rate_buy' => $rateData['buy'],
                'rate_sell' => $rateData['sell'],
                'source' => 'api',
                'fetched_at' => $now,
            ])->values()->all(),
            ['currency_code', 'branch_id'],
            ['rate_buy', 'rate_sell', 'source', 'fetched_at']
        );
    }

    protected function logRatesToHistory(array $rates, ?int $branchId = null): void
    {
        $today = now()->toDateString();
        $userId = auth()->id() ?? config('cems.system_user_id', 1);

        $existing = ExchangeRateHistory::where('branch_id', $branchId)
            ->whereIn('currency_code', array_keys($rates))
            ->whereDate('effective_date', $today)
            ->pluck('currency_code')
            ->flip();

        $rows = collect($rates)
            ->reject(fn ($_, $currencyCode) => $existing->has($currencyCode))
            ->map(fn ($rateData, $currencyCode) => [
                'currency_code' => $currencyCode,
                'branch_id' => $branchId,
                'rate' => $rateData['mid'],
                'effective_date' => $today,
                'created_by' => $userId,
                'notes' => "API fetch - Buy: {$rateData['buy']}, Sell: {$rateData['sell']}".($branchId ? " (Branch: {$branchId})" : ''),
            ])->values()->all();

        if (! empty($rows)) {
            ExchangeRateHistory::insert($rows);
        }
    }

    public function getRateForCurrency(string $currency, ?int $branchId = null): ?array
    {
        $rates = $this->fetchLatestRates($branchId);

        return $rates[$currency] ?? null;
    }

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

        return match ($type) {
            'buy' => $exchangeRate->rate_buy,
            'sell' => $exchangeRate->rate_sell,
            'mid' => $this->roundRate(
                $this->mathService->divide(
                    $this->mathService->add($exchangeRate->rate_buy, $exchangeRate->rate_sell),
                    '2'
                )
            ),
            default => $exchangeRate->rate_buy,
        };
    }

    public function validateRateDeviation(
        string $submittedRate,
        string $currencyCode,
        string $type = 'buy',
        ?int $branchId = null
    ): array {
        $marketRate = $this->getCurrentRate($currencyCode, $type, $branchId);

        if ($marketRate === null) {
            return [
                'valid' => true,
                'reason' => null,
                'deviation_percent' => null,
                'max_allowed' => $this->rateThresholds()['max_deviation_percent'],
            ];
        }

        $deviation = $this->mathService->abs(
            $this->mathService->subtract($submittedRate, $marketRate)
        );

        $deviationPercent = $this->mathService->divide(
            $this->mathService->multiply($deviation, '100'),
            $marketRate
        );

        $maxAllowed = $this->rateThresholds()['max_deviation_percent'];

        $isValid = $this->mathService->compare($deviationPercent, $maxAllowed) <= 0;

        return [
            'valid' => $isValid,
            'reason' => $isValid ? null : "Rate deviation {$deviationPercent}% exceeds maximum allowed {$maxAllowed}%",
            'deviation_percent' => $this->roundRate($deviationPercent),
            'max_allowed' => $maxAllowed,
            'market_rate' => $marketRate,
            'submitted_rate' => $submittedRate,
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
