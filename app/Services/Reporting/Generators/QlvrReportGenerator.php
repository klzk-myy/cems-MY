<?php

namespace App\Services\Reporting\Generators;

use App\Enums\TransactionType;
use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\TransactionReportQuery;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Support\DbDate;
use App\ValueObjects\Quarter;
use Carbon\Carbon;
use Illuminate\Support\LazyCollection;

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

        return array_merge(
            $this->summarize($quarter, $startDate, $endDate),
            ['data' => iterator_to_array($this->transactionRows($startDate, $endDate), false)]
        );
    }

    public function generateCsv(string $quarter): string
    {
        $quarterVo = Quarter::fromString($quarter);
        $startDate = $quarterVo->startDate();
        $endDate = $quarterVo->endDate();

        $data = $this->summarize($quarter, $startDate, $endDate);
        $filename = "QLVR_{$quarter}.csv";

        $titleRows = [
            ['BNM Quarterly Large Value Transaction Report'],
            ['Quarter', $data['quarter']],
            ['Period', $data['period_start'].' to '.$data['period_end']],
            ['Total Transactions', $data['total_transactions']],
            ['Total Amount (MYR)', number_format($data['total_amount_myr'], 2)],
        ];

        $headers = ['Transaction_ID', 'Date', 'Customer_Name', 'Amount_Local', 'Currency', 'Transaction_Type'];

        $rows = function () use ($startDate, $endDate) {
            foreach ($this->transactionRows($startDate, $endDate) as $row) {
                yield array_values($row);
            }
        };

        return $this->csvReportWriter->writeWithTitleRows($filename, $titleRows, $headers, $rows());
    }

    /**
     * Compute the report totals in SQL instead of hydrating the full quarter
     * into memory. SUM() over a DECIMAL column returns an exact decimal
     * string, so there is no float-precision risk; the returned breakdown
     * totals are combined with bcmath for the grand total.
     *
     * @return array<string, mixed>
     */
    protected function summarize(string $quarter, Carbon $startDate, Carbon $endDate): array
    {
        $monthExpr = DbDate::monthBucket('created_at');

        $base = fn () => $this->transactionReportQuery
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->where('amount_myr', '>=', $this->thresholdService->getLargeTransactionThreshold());

        $monthlyAggregates = $base()
            ->selectRaw("{$monthExpr} as month, COUNT(*) as count, SUM(amount_myr) as total")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthlyBreakdown = [];
        $totalTransactions = 0;
        $totalAmountMyr = '0';

        for ($m = 0; $m < 3; $m++) {
            $monthDate = $startDate->copy()->addMonths($m);
            $key = $monthDate->format('Y-m');
            $aggregate = $monthlyAggregates->get($key);
            // count/total are selectRaw aliases, not model attributes — read
            // them through getAttribute() so static analysis stays honest.
            $count = (int) ($aggregate?->getAttribute('count') ?? 0);
            $total = (string) ($aggregate?->getAttribute('total') ?? '0');

            $totalTransactions += $count;
            $totalAmountMyr = $this->mathService->add($totalAmountMyr, $total);

            $monthlyBreakdown[] = [
                'month' => $key,
                'count' => $count,
                'total_amount_myr' => $total,
            ];
        }

        $byCurrency = $base()
            ->selectRaw('currency_code, COUNT(*) as count, SUM(amount_myr) as total')
            ->groupBy('currency_code')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency_code,
                'count' => (int) $row->getAttribute('count'),
                'total_amount_myr' => (string) $row->getAttribute('total'),
            ])
            ->values();

        return [
            'quarter' => $quarter,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'total_transactions' => $totalTransactions,
            'total_amount_myr' => $totalAmountMyr,
            'monthly_breakdown' => $monthlyBreakdown,
            'by_currency' => $byCurrency,
        ];
    }

    /**
     * Stream the report's detail rows one at a time so a large quarter never
     * materializes every transaction model at once. Only the columns the
     * report renders are selected, and the customer name is eager loaded with
     * a narrow column list.
     *
     * lazyById() (not cursor()): cursor() hydrates row-by-row and never applies
     * eager loads, so ->customer would lazy-load per row — an N+1 that also
     * trips preventLazyLoading in dev/test. lazyById() keyset-pages by id
     * (monotonic with created_at), which both eager-loads each chunk and stays
     * stable when created_at ties or rows are inserted mid-report.
     *
     * @return LazyCollection<int, array{Transaction_ID: string, Date: string, Customer_Name: string, Amount_Local: string, Currency: string, Transaction_Type: TransactionType}>
     */
    protected function transactionRows(Carbon $startDate, Carbon $endDate): LazyCollection
    {
        return $this->transactionReportQuery
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->with('customer:id,full_name')
            ->select(['id', 'created_at', 'customer_id', 'amount_myr', 'currency_code', 'type'])
            ->where('amount_myr', '>=', $this->thresholdService->getLargeTransactionThreshold())
            ->lazyById()
            ->map(
                /** @return array<string, mixed> */
                fn ($txn) => [
                    'Transaction_ID' => 'TXN-'.str_pad((string) $txn->id, 8, '0', STR_PAD_LEFT),
                    'Date' => $txn->created_at->format('Y-m-d'),
                    'Customer_Name' => $this->maskName($txn->customer->full_name),
                    'Amount_Local' => $txn->amount_myr,
                    'Currency' => $txn->currency_code,
                    'Transaction_Type' => $txn->type,
                ]
            );
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
