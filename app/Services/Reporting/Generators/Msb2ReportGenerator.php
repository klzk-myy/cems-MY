<?php

namespace App\Services\Reporting\Generators;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\TransactionReportQuery;
use Illuminate\Support\Collection;

class Msb2ReportGenerator
{
    public function __construct(
        protected TransactionReportQuery $transactionReportQuery,
        protected CsvReportWriter $csvReportWriter,
    ) {}

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

        $transactions = $query->completed()
            ->forDateRange($date, $date)
            ->select(['currency_code', 'type', 'rate', 'quantity'])
            ->get()
            ->groupBy('currency_code');

        $rows = [];

        foreach ($currencies as $currency) {
            $row = $summary->get($currency->code);
            $currencyTxns = $transactions->get($currency->code, collect());

            $buyTxns = $currencyTxns->where('type', TransactionType::Buy->value);
            $sellTxns = $currencyTxns->where('type', TransactionType::Sell->value);

            // Closing is the current company-wide stock; opening is derived
            // by unwinding the day's net flow (closing = opening + buys −
            // sells). Emitting the live quantity for both columns filed the
            // same snapshot twice — wrong whenever the report is generated
            // after the business date or intraday.
            $closingPosition = bcadd((string) ($positions[$currency->code] ?? '0'), '0', 4);
            $netFlow = bcsub(
                $this->sumColumn($buyTxns, 'quantity'),
                $this->sumColumn($sellTxns, 'quantity'),
                4
            );
            $openingPosition = bcsub($closingPosition, $netFlow, 4);

            $rows[] = [
                'Date' => $date,
                'Currency' => $currency->code,
                'Buy_Volume_MYR' => $row ? (string) $row->buy_volume : '0',
                'Buy_Count' => $row ? (int) $row->buy_count : 0,
                'Sell_Volume_MYR' => $row ? (string) $row->sell_volume : '0',
                'Sell_Count' => $row ? (int) $row->sell_count : 0,
                'Avg_Buy_Rate' => $this->averageRate($buyTxns),
                'Avg_Sell_Rate' => $this->averageRate($sellTxns),
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
     * Average a collection of DECIMAL rate strings without float precision loss.
     *
     * Rates are stored at decimal(18,8), so the average keeps 8 decimals.
     * Scale-8 summation keeps low-value per-unit rates (e.g. IDR 0.000235)
     * from collapsing to zero at the default scale of 4.
     *
     * @param  Collection<int, Transaction>  $txns
     */
    private function averageRate(Collection $txns): string
    {
        $count = $txns->count();

        if ($count === 0) {
            return '0';
        }

        $total = '0';
        foreach ($txns as $txn) {
            /** @var numeric-string $rate */
            $rate = (string) $txn->rate;
            $total = bcadd($total, $rate, 8);
        }

        return bcdiv($total, (string) $count, 8);
    }

    /**
     * Sum a DECIMAL column on a transaction collection with bcmath.
     *
     * @param  Collection<int, Transaction>  $txns
     * @return numeric-string
     */
    private function sumColumn(Collection $txns, string $column): string
    {
        $total = '0';
        foreach ($txns as $txn) {
            $value = (string) ($txn->{$column} ?? '0');
            if (is_numeric($value) && $value !== '') {
                $total = bcadd($total, $value, 4);
            }
        }

        return $total;
    }
}
