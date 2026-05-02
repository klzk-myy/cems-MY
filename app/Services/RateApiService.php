<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RateApiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected MathService $mathService;

    protected string $spread;

    protected string $maxDeviationPercent;

    protected int $precision;

    protected int $cacheDuration;

    public function __construct(?MathService $mathService = null)
    {
        $this->mathService = $mathService ?? new MathService;
        $this->apiKey = config('services.exchange_rate_api.key') ?? '';
        $this->baseUrl = 'https://api.exchangerate-api.com/v4';
        $this->spread = config('thresholds.rates.spread', '0.02');
        $this->maxDeviationPercent = config('thresholds.rates.max_deviation_percent', '0.05');
        $this->precision = (int) config('thresholds.rates.precision', 4);
        $this->cacheDuration = (int) config('thresholds.rates.cache_duration', 60);
    }

    public function fetchLatestRates(?int $branchId = null): array
    {
        $cacheKey = $branchId ? "exchange_rates_branch_{$branchId}" : 'exchange_rates';

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($branchId) {
            $response = Http::get("{$this->baseUrl}/latest/MYR");

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch exchange rates: '.$response->body());
            }

            $data = $response->json();

            if (! isset($data['rates'])) {
                throw new \RuntimeException('Invalid API response format');
            }

            $processed = $this->processRates($data['rates'], $data['time_last_updated'] ?? time());

            $this->storeRatesToTable($processed, $branchId);
            $this->logRatesToHistory($processed, $branchId);

            return $processed;
        });
    }

    protected function processRates(array $rates, $timestamp): array
    {
        $processed = [];
        $currencies = ['USD', 'EUR', 'GBP', 'SGD', 'AUD', 'CAD', 'CHF', 'JPY'];

        foreach ($currencies as $currency) {
            if (isset($rates[$currency])) {
                $rate = $rates[$currency];
                $rateStr = (string) $rate;

                // Apply full spread on each side of mid rate
                // buy = mid * (1 - spread), sell = mid * (1 + spread)
                // e.g., 2% spread: buy is 2% below mid, sell is 2% above mid
                $buyRate = $this->mathService->multiply($rateStr, $this->mathService->subtract('1', $this->spread));
                $sellRate = $this->mathService->multiply($rateStr, $this->mathService->add('1', $this->spread));

                $processed[$currency] = [
                    'buy' => $this->roundRate($buyRate),
                    'sell' => $this->roundRate($sellRate),
                    'mid' => $this->roundRate($rateStr),
                    'timestamp' => $timestamp,
                ];
            }
        }

        return $processed;
    }

    protected function roundRate(string $rate): string
    {
        return bcadd($rate, '0', $this->precision);
    }

    protected function storeRatesToTable(array $rates, ?int $branchId = null): void
    {
        $now = now();

        foreach ($rates as $currencyCode => $rateData) {
            $query = ExchangeRate::where('currency_code', $currencyCode);
            if ($branchId !== null) {
                $query->forBranch($branchId);
            }

            $query->updateOrCreate(
                ['currency_code' => $currencyCode, 'branch_id' => $branchId],
                [
                    'rate_buy' => $rateData['buy'],
                    'rate_sell' => $rateData['sell'],
                    'source' => 'api',
                    'fetched_at' => $now,
                ]
            );
        }
    }

    protected function logRatesToHistory(array $rates, ?int $branchId = null): void
    {
        $today = now()->toDateString();
        $userId = auth()->id() ?? 1;

        foreach ($rates as $currencyCode => $rateData) {
            $query = ExchangeRateHistory::forCurrency($currencyCode)
                ->whereDate('effective_date', $today);

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            $exists = $query->exists();

            if (! $exists) {
                ExchangeRateHistory::create([
                    'currency_code' => $currencyCode,
                    'rate' => $rateData['mid'],
                    'effective_date' => $today,
                    'created_by' => $userId,
                    'notes' => "API fetch - Buy: {$rateData['buy']}, Sell: {$rateData['sell']}".($branchId ? " (Branch: {$branchId})" : ''),
                ]);
            }
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
            $query->forBranch($branchId);
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
                'max_allowed' => $this->maxDeviationPercent,
            ];
        }

        $deviation = $this->mathService->abs(
            $this->mathService->subtract($submittedRate, $marketRate)
        );

        $deviationPercent = $this->mathService->divide(
            $this->mathService->multiply($deviation, '100'),
            $marketRate
        );

        $maxAllowed = $this->maxDeviationPercent;

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
        $cacheKey = $branchId ? "exchange_rates_branch_{$branchId}" : 'exchange_rates';
        Cache::forget($cacheKey);
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
            $percentChange = bcadd(bcmul($percentChangeRaw, '100', 6), '0', 2);
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
        return $this->spread;
    }

    public function getMaxDeviationPercent(): string
    {
        return $this->maxDeviationPercent;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }
}
