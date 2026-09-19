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

        $positions = CurrencyPosition::whereIn('currency_code', $currencyCodes)
            ->get()
            ->keyBy('currency_code');

        $transactions = $query->completed()
            ->forDateRange($date, $date)
            ->select(['currency_code', 'type', 'rate'])
            ->get()
            ->groupBy('currency_code');

        $rows = [];

        foreach ($currencies as $currency) {
            $row = $summary->get($currency->code);
            $currencyTxns = $transactions->get($currency->code, collect());
            $position = $positions->get($currency->code);

            $buyTxns = $currencyTxns->where('type', TransactionType::Buy->value);
            $sellTxns = $currencyTxns->where('type', TransactionType::Sell->value);

            $rows[] = [
                'Date' => $date,
                'Currency' => $currency->code,
                'Buy_Volume_MYR' => $row ? (string) $row->buy_volume : '0',
                'Buy_Count' => $row ? (int) $row->buy_count : 0,
                'Sell_Volume_MYR' => $row ? (string) $row->sell_volume : '0',
                'Sell_Count' => $row ? (int) $row->sell_count : 0,
                'Avg_Buy_Rate' => $this->averageRate($buyTxns),
                'Avg_Sell_Rate' => $this->averageRate($sellTxns),
                'Opening_Position' => $position ? $position->quantity : '0',
                'Closing_Position' => $position ? $position->quantity : '0',
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
}
