<?php

namespace App\Services;

use App\Models\ExchangeRateHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RateApiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected int $cacheDuration = 60; // seconds

    protected MathService $mathService;

    public function __construct(?MathService $mathService = null)
    {
        $this->mathService = $mathService ?? new MathService;
        $this->apiKey = config('services.exchange_rate_api.key') ?? '';
        $this->baseUrl = 'https://api.exchangerate-api.com/v4';
    }

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

            // Log rates to history table
            $this->logRatesToHistory($processed);

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

                // Add spread for buy/sell
                $spreadStr = '0.02'; // 2% spread
                $half = $this->mathService->divide($spreadStr, '2'); // '0.01'
                $rateStr = (string) $rate;

                $buyRate = $this->mathService->multiply($rateStr, $this->mathService->subtract('1', $half));
                $sellRate = $this->mathService->multiply($rateStr, $this->mathService->add('1', $half));

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
        return bcadd($rate, '0', 6);
    }

    /**
     * Log rates to history table
     */
    protected function logRatesToHistory(array $rates): void
    {
        $today = now()->toDateString();
        $userId = auth()->id();

        foreach ($rates as $currencyCode => $rateData) {
            // Check if we already have an entry for today
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

    public function getRateForCurrency(string $currency): ?array
    {
        $rates = $this->fetchLatestRates();

        return $rates[$currency] ?? null;
    }

    public function clearCache(): void
    {
        Cache::forget('exchange_rates');
    }

    /**
     * Get rate trend for a currency over specified days
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

        // Calculate trend
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
}
