<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Rate API Service
 *
 * Fetches exchange rates from external API, applies configurable spread,
 * and stores rates in the exchange_rates table for daily operations.
 */
class RateApiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected MathService $mathService;

    /**
     * Get spread from thresholds config (default 2%)
     */
    protected string $spread;

    /**
     * Get max deviation percent from thresholds config (default 5%)
     */
    protected string $maxDeviationPercent;

    /**
     * Precision for rate calculations
     */
    protected int $precision;

    /**
     * Cache duration in seconds
     */
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

    /**
     * Fetch latest rates from API, apply spread, and store in exchange_rates table.
     *
     * @return array Processed rates by currency
     */
    public function fetchLatestRates(): array
    {
        return Cache::remember('exchange_rates', $this->cacheDuration, function () {
            $response = Http::get("{$this->baseUrl}/latest/MYR");

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch exchange rates: '.$response->body());
            }

            $data = $response->json();

            if (! isset($data['rates'])) {
                throw new \RuntimeException('Invalid API response format');
            }

            $processed = $this->processRates($data['rates'], $data['time_last_updated'] ?? time());

            // Store rates in exchange_rates table
            $this->storeRatesToTable($processed);

            // Log rates to history table
            $this->logRatesToHistory($processed);

            return $processed;
        });
    }

    /**
     * Process raw API rates and apply spread.
     *
     * @param  array  $rates  Raw rates from API
     * @param  int  $timestamp  API timestamp
     * @return array Processed rates
     */
    protected function processRates(array $rates, $timestamp): array
    {
        $processed = [];
        $currencies = ['USD', 'EUR', 'GBP', 'SGD', 'AUD', 'CAD', 'CHF', 'JPY'];

        $halfSpread = $this->mathService->divide($this->spread, '2');

        foreach ($currencies as $currency) {
            if (isset($rates[$currency])) {
                $rate = $rates[$currency];
                $rateStr = (string) $rate;

                // Apply spread: buy rate = mid - halfspread, sell rate = mid + halfspread
                $buyRate = $this->mathService->multiply($rateStr, $this->mathService->subtract('1', $halfSpread));
                $sellRate = $this->mathService->multiply($rateStr, $this->mathService->add('1', $halfSpread));

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

    /**
     * Round rate to configured precision.
     *
     * @param  string  $rate  Rate to round
     * @return string Rounded rate
     */
    protected function roundRate(string $rate): string
    {
        return bcadd($rate, '0', $this->precision);
    }

    /**
     * Store rates to exchange_rates table.
     * This table is used for daily rate management before counter opening.
     *
     * @param  array  $rates  Processed rates
     */
    protected function storeRatesToTable(array $rates): void
    {
        $now = now();

        foreach ($rates as $currencyCode => $rateData) {
            ExchangeRate::updateOrCreate(
                ['currency_code' => $currencyCode],
                [
                    'rate_buy' => $rateData['buy'],
                    'rate_sell' => $rateData['sell'],
                    'source' => 'api',
                    'fetched_at' => $now,
                ]
            );
        }
    }

    /**
     * Log rates to history table for audit trail.
     *
     * @param  array  $rates  Processed rates
     */
    protected function logRatesToHistory(array $rates): void
    {
        $today = now()->toDateString();
        $userId = auth()->id() ?? 1;

        foreach ($rates as $currencyCode => $rateData) {
            $exists = ExchangeRateHistory::forCurrency($currencyCode)
                ->whereDate('effective_date', $today)
                ->exists();

            if (! $exists) {
                ExchangeRateHistory::create([
                    'currency_code' => $currencyCode,
                    'rate' => $rateData['mid'],
                    'effective_date' => $today,
                    'created_by' => $userId,
                    'notes' => "API fetch - Buy: {$rateData['buy']}, Sell: {$rateData['sell']}",
                ]);
            }
        }
    }

    /**
     * Get rate for a specific currency from cached/fresh rates.
     *
     * @param  string  $currency  Currency code
     * @return array|null Rate data with buy/sell/mid or null if not found
     */
    public function getRateForCurrency(string $currency): ?array
    {
        $rates = $this->fetchLatestRates();

        return $rates[$currency] ?? null;
    }

    /**
     * Get the current market rate from exchange_rates table.
     * Used for deviation validation.
     *
     * @param  string  $currencyCode  Currency code
     * @param  string  $type  'buy' or 'sell'
     * @return string|null Current rate or null if not found
     */
    public function getCurrentRate(string $currencyCode, string $type = 'mid'): ?string
    {
        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (! $exchangeRate) {
            return null;
        }

        return match ($type) {
            'buy' => $exchangeRate->rate_buy,
            'sell' => $exchangeRate->rate_sell,
            default => $exchangeRate->rate_buy, // Default to buy for backward compatibility
        };
    }

    /**
     * Validate a submitted rate against current market rate.
     *
     * Returns array with 'valid' boolean and 'reason' if invalid.
     *
     * @param  string  $submittedRate  Rate submitted by user
     * @param  string  $currencyCode  Currency code
     * @param  string  $type  Transaction type ('buy' or 'sell')
     * @return array{valid: bool, reason: string|null, deviation_percent: string|null, max_allowed: string}
     */
    public function validateRateDeviation(
        string $submittedRate,
        string $currencyCode,
        string $type = 'buy'
    ): array {
        $marketRate = $this->getCurrentRate($currencyCode, $type);

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

    /**
     * Clear the rates cache to force fresh fetch.
     */
    public function clearCache(): void
    {
        Cache::forget('exchange_rates');
    }

    /**
     * Get rate trend for a currency over specified days.
     *
     * @param  string  $currencyCode  Currency code
     * @param  int  $days  Number of days to look back
     * @return array Trend data with dates and rates
     */
    public function getRateTrend(string $currencyCode, int $days = 30): array
    {
        $endDate = now()->toDateString();
        $startDate = now()->subDays($days)->toDateString();

        $histories = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange($startDate, $endDate)
            ->orderBy('effective_date', 'asc')
            ->get();

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

    /**
     * Get the configured spread percentage.
     */
    public function getSpread(): string
    {
        return $this->spread;
    }

    /**
     * Get the configured max deviation percentage.
     */
    public function getMaxDeviationPercent(): string
    {
        return $this->maxDeviationPercent;
    }

    /**
     * Get the configured precision.
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }
}
