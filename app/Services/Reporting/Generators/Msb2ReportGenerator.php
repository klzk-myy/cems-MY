<?php

namespace App\Services\Reporting\Generators;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\TransactionReportQuery;
use Illuminate\Support\Facades\Cache;

class Msb2ReportGenerator
{
    /**
     * Report data for a past date is deterministic — it never changes once
     * the business day is closed. Cache the computed dataset under the
     * 'reports' tag so reloading the page (or re-running the scheduled
     * command within the TTL) serves from cache instead of re-aggregating
     * the full day's transactions. Tagged so a transaction write can flush
     * all report caches at once.
     */
    private const REPORT_DATA_TTL = 300;

    public function __construct(
        protected TransactionReportQuery $transactionReportQuery,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    /**
     * Report data for a past date is deterministic — it never changes once
     * the business day is closed. Cache the computed dataset under the
     * 'reports' tag so reloading the page (or re-running the scheduled
     * command within the TTL) serves from cache instead of re-aggregating
     * the full day's transactions. Tagged so a transaction write can flush
     * all report caches at once.
     */
    public function generate(string $date): string
    {
        $query = $this->transactionReportQuery;

        $summary = $query->buySellSummary(
            $query->completed()
                ->forDateRange($date, $date)
                ->select('currency_code')
                ->orderBy('currency_code'),
            'currency_code',
            'quantity'
        );

        $filename = "MSB2_{$date}.csv";

        $headers = [
            'Date',
            'Currency',
            'Buy_Volume',
            'Buy_Count',
            'Sell_Volume',
            'Sell_Count',
        ];

        $rows = [];
        foreach ($summary as $row) {
            $rows[] = [
                $date,
                $row->currency_code,
                (string) $row->buy_volume,
                (int) $row->buy_count,
                (string) $row->sell_volume,
                (int) $row->sell_count,
            ];
        }

        return $this->csvReportWriter->write($filename, $headers, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateData(string $date): array
    {
        $cacheKey = "report_data:msb2:{$date}";

        return Cache::tags(['reports'])->remember($cacheKey, self::REPORT_DATA_TTL, function () use ($date) {
            return $this->computeData($date);
        });
    }

    /**
     * Compute the MSB2 dataset. Separated from generateData() so the cache
     * wrapper stays thin and the computation stays testable.
     *
     * @return array<string, mixed>
     */
    private function computeData(string $date): array
    {
        $query = $this->transactionReportQuery;

        $summary = $query->buySellSummary(
            $query->completed()->forDateRange($date, $date)->select('currency_code')->orderBy('currency_code'),
            'currency_code'
        )->keyBy('currency_code');

        $currencies = Currency::where('is_active', true)->get();
        $currencyCodes = $currencies->pluck('code')->toArray();

        // Positions are per-branch rows — keyBy('currency_code') would keep
        // only the last branch's balance per currency. The regulator-facing
        // figure is the company-wide aggregate.
        $positions = CurrencyPosition::whereIn('currency_code', $currencyCodes)
            ->selectRaw('currency_code, SUM(quantity) as total_quantity')
            ->groupBy('currency_code')
            ->pluck('total_quantity', 'currency_code');

        // Avg buy/sell rates computed in SQL — avoids hydrating every
        // transaction of the day into PHP memory just to average one column.
        $avgRates = $query->completed()
            ->forDateRange($date, $date)
            ->selectRaw('currency_code, AVG(CASE WHEN type = ? THEN rate END) avg_buy_rate, AVG(CASE WHEN type = ? THEN rate END) avg_sell_rate', [
                TransactionType::Buy->value,
                TransactionType::Sell->value,
            ])
            ->groupBy('currency_code')
            ->get()
            ->keyBy('currency_code');

        $rows = [];

        foreach ($currencies as $currency) {
            $row = $summary->get($currency->code);
            $avgRow = $avgRates->get($currency->code);

            /** @var numeric-string $rawClosing */
            $rawClosing = (string) ($positions[$currency->code] ?? '0');
            $closingPosition = bcadd($rawClosing, '0', 4);

            /** @var numeric-string $buyVolume */
            $buyVolume = $row ? (string) $row->buy_volume : '0';
            /** @var numeric-string $sellVolume */
            $sellVolume = $row ? (string) $row->sell_volume : '0';
            $netFlow = bcsub($buyVolume, $sellVolume, 4);
            $openingPosition = bcsub($closingPosition, $netFlow, 4);

            $rows[] = [
                'Date' => $date,
                'Currency' => $currency->code,
                'Buy_Volume_MYR' => $buyVolume,
                'Buy_Count' => $row ? (int) $row->buy_count : 0,
                'Sell_Volume_MYR' => $sellVolume,
                'Sell_Count' => $row ? (int) $row->sell_count : 0,
                'Avg_Buy_Rate' => $this->roundRate($avgRow ? $avgRow->getAttribute('avg_buy_rate') : null),
                'Avg_Sell_Rate' => $this->roundRate($avgRow ? $avgRow->getAttribute('avg_sell_rate') : null),
                'Opening_Position' => $openingPosition,
                'Closing_Position' => $closingPosition,
            ];
        }

        return [
            'date' => $date,
            'generated_at' => now()->toIso8601String(),
            'data' => $rows,
        ];
    }

    /**
     * Round a SQL AVG result (string|null) to the per-unit rate scale.
     *
     * AVG() over a DECIMAL(18,8) column returns a string that bcround
     * accepts as numeric-string after the numeric guard.
     */
    private function roundRate(mixed $rate): string
    {
        if ($rate === null || ! is_numeric($rate)) {
            return '0';
        }

        return bcround((string) $rate, 8);
    }
}
