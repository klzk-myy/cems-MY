<?php

namespace App\Services\Reporting\Generators;

use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\TransactionReportQuery;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\ValueObjects\Quarter;

class QlvrReportGenerator
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected TransactionReportQuery $transactionReportQuery,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(string $quarter): array
    {
        $quarterVo = Quarter::fromString($quarter);
        $startDate = $quarterVo->startDate();
        $endDate = $quarterVo->endDate();

        $transactions = $this->transactionReportQuery
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->with(['customer', 'user'])
            ->where('amount_local', '>=', $this->thresholdService->getLargeTransactionThreshold())
            ->orderBy('created_at')
            ->get();

        // Collection::sum() casts DECIMAL strings to float; use bcmath so large
        // monetary totals in the report do not lose precision.
        $sumAmounts = function ($txns) {
            $total = '0';
            foreach ($txns as $txn) {
                $total = $this->mathService->add($total, (string) $txn->amount_local);
            }

            return $total;
        };

        $monthlyBreakdown = [];
        for ($m = 0; $m < 3; $m++) {
            $monthDate = $startDate->copy()->addMonths($m);
            $monthTxns = $transactions->filter(function ($txn) use ($monthDate) {
                return $txn->created_at->format('Y-m') === $monthDate->format('Y-m');
            });

            $monthlyBreakdown[] = [
                'month' => $monthDate->format('Y-m'),
                'count' => $monthTxns->count(),
                'total_amount' => $sumAmounts($monthTxns),
            ];
        }

        $byCurrency = $transactions->groupBy('currency_code')->map(function ($txns) use ($sumAmounts) {
            return [
                'currency' => $txns->first()->currency_code,
                'count' => $txns->count(),
                'total_amount' => $sumAmounts($txns),
            ];
        })->values();

        return [
            'quarter' => $quarter,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'total_transactions' => $transactions->count(),
            'total_amount' => $sumAmounts($transactions),
            'monthly_breakdown' => $monthlyBreakdown,
            'by_currency' => $byCurrency,
            'data' => $transactions->map(function ($txn) {
                return [
                    'Transaction_ID' => 'TXN-'.str_pad((string) $txn->id, 8, '0', STR_PAD_LEFT),
                    'Date' => $txn->created_at->format('Y-m-d'),
                    'Customer_Name' => $this->maskName($txn->customer->full_name),
                    'Amount_Local' => $txn->amount_local,
                    'Currency' => $txn->currency_code,
                    'Transaction_Type' => $txn->type,
                ];
            })->toArray(),
        ];
    }

    public function generateCsv(string $quarter): string
    {
        $data = $this->generate($quarter);
        $filename = "QLVR_{$quarter}.csv";

        $titleRows = [
            ['BNM Quarterly Large Value Transaction Report'],
            ['Quarter', $data['quarter']],
            ['Period', $data['period_start'].' to '.$data['period_end']],
            ['Total Transactions', $data['total_transactions']],
            ['Total Amount (MYR)', number_format($data['total_amount'], 2)],
        ];

        $headers = ['Transaction_ID', 'Date', 'Customer_Name', 'Amount_Local', 'Currency', 'Transaction_Type'];
        $rows = [];
        foreach ($data['data'] as $row) {
            $rows[] = array_values($row);
        }

        return $this->csvReportWriter->writeWithTitleRows($filename, $titleRows, $headers, $rows);
    }

    protected function maskName(string $name): string
    {
        $parts = explode(' ', $name);
        $masked = [];

        foreach ($parts as $part) {
            if (strlen($part) > 2) {
                $masked[] = substr($part, 0, 2).str_repeat('*', strlen($part) - 2);
            } else {
                $masked[] = $part;
            }
        }

        return implode(' ', $masked);
    }
}
