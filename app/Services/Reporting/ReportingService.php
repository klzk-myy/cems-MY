<?php

namespace App\Services\Reporting;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Contracts\ReportingServiceInterface;
use App\Services\System\EncryptionService;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportingService implements ReportingServiceInterface
{
    protected EncryptionService $encryptionService;

    protected MathService $mathService;

    protected const LARGE_VALUE_THRESHOLD = '50000';

    public function __construct(
        EncryptionService $encryptionService,
        MathService $mathService
    ) {
        $this->encryptionService = $encryptionService;
        $this->mathService = $mathService;
    }

    public function generateMSB2(string $date): string
    {
        $buyType = TransactionType::Buy->value;
        $sellType = TransactionType::Sell->value;

        $summary = Transaction::completed()
            ->forDateRange($date, $date)
            ->select('currency_code')
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount_foreign ELSE 0 END) as buy_volume', [$buyType])
            ->selectRaw('COUNT(CASE WHEN type = ? THEN 1 END) as buy_count', [$buyType])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount_foreign ELSE 0 END) as sell_volume', [$sellType])
            ->selectRaw('COUNT(CASE WHEN type = ? THEN 1 END) as sell_count', [$sellType])
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();

        $filename = "MSB2_{$date}.csv";
        $filepath = "reports/{$filename}";

        // Ensure the reports directory exists
        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');
        if (! $csv) {
            throw new \RuntimeException("Failed to open report file for writing: {$filepath}");
        }

        fputcsv($csv, [
            'Date',
            'Currency',
            'Buy_Volume',
            'Buy_Count',
            'Sell_Volume',
            'Sell_Count',
        ]);

        foreach ($summary as $row) {
            fputcsv($csv, [
                $date,
                $row->currency_code,
                (string) $row->buy_volume,
                (int) $row->buy_count,
                (string) $row->sell_volume,
                (int) $row->sell_count,
            ]);
        }

        fclose($csv);

        return $filepath;
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

    public function generateMSB2Data(string $date): array
    {
        $transactions = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($date, $date)
            ->get();

        $currencies = Currency::where('is_active', true)->get();
        $currencyCodes = $currencies->pluck('code')->toArray();

        $positions = CurrencyPosition::whereIn('currency_code', $currencyCodes)
            ->get()
            ->keyBy('currency_code');

        $rows = [];

        foreach ($currencies as $currency) {
            $buyTxns = $transactions->where('currency_code', $currency->code)->where('type', 'Buy');
            $sellTxns = $transactions->where('currency_code', $currency->code)->where('type', 'Sell');
            $position = $positions->get($currency->code);

            $rows[] = [
                'Date' => $date,
                'Currency' => $currency->code,
                'Buy_Volume_MYR' => (string) $buyTxns->sum('amount_local'),
                'Buy_Count' => $buyTxns->count(),
                'Sell_Volume_MYR' => (string) $sellTxns->sum('amount_local'),
                'Sell_Count' => $sellTxns->count(),
                'Avg_Buy_Rate' => (string) ($buyTxns->avg('rate') ?? '0'),
                'Avg_Sell_Rate' => (string) ($sellTxns->avg('rate') ?? '0'),
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

    public function generateCurrencyPositionReport(): array
    {
        $positions = CurrencyPosition::with('currency')->get();

        $data = [];
        $totalUnrealizedPnl = '0';

        foreach ($positions as $position) {
            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => $position->quantity,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'unrealized_gain_loss' => $position->unrealized_gain_loss,
            ];
            $totalUnrealizedPnl = $this->mathService->add($totalUnrealizedPnl, $position->unrealized_gain_loss ?? '0');
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'positions' => $data,
            'total_unrealized_pnl' => $totalUnrealizedPnl,
        ];
    }

    public function generateUnrealizedPnLReport(): array
    {
        $positions = CurrencyPosition::with('currency')
            ->whereRaw('unrealized_gain_loss != 0')
            ->get();

        $data = [];
        $totalGain = '0';
        $totalLoss = '0';

        foreach ($positions as $position) {
            $pnl = $position->unrealized_gain_loss ?? '0';

            if ($this->mathService->compare($pnl, '0') >= 0) {
                $totalGain = $this->mathService->add($totalGain, $pnl);
            } else {
                $totalLoss = $this->mathService->add($totalLoss, $pnl);
            }

            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => $position->quantity,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'unrealized_gain_loss' => $pnl,
                'is_gain' => $this->mathService->compare($pnl, '0') >= 0,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'positions' => $data,
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
            'net_pnl' => $this->mathService->add($totalGain, $totalLoss),
        ];
    }

    public function generateFormLMCA(string $month): array
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $currencies = Currency::where('is_active', true)->get();
        $currencyCodes = $currencies->pluck('code')->toArray();
        $currencyData = [];

        $allTxns = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->whereIn('currency_code', $currencyCodes)
            ->get()
            ->groupBy('currency_code');

        $positions = CurrencyPosition::whereIn('currency_code', $currencyCodes)
            ->get()
            ->keyBy('currency_code');

        foreach ($currencies as $currency) {
            $currencyTxns = $allTxns->get($currency->code, collect());
            $buyTxns = $currencyTxns->where('type', 'Buy');
            $sellTxns = $currencyTxns->where('type', 'Sell');

            $openingPosition = $positions->get($currency->code);

            $currencyData[] = [
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'buy_count' => $buyTxns->count(),
                'buy_volume' => $buyTxns->sum('amount_foreign'),
                'buy_value_myr' => $buyTxns->sum('amount_local'),
                'sell_count' => $sellTxns->count(),
                'sell_volume' => $sellTxns->sum('amount_foreign'),
                'sell_value_myr' => $sellTxns->sum('amount_local'),
                'opening_stock' => $openingPosition ? $openingPosition->quantity : '0',
                'closing_stock' => $openingPosition ? $openingPosition->quantity : '0',
            ];
        }

        $customerCount = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->distinct('customer_id')
            ->count('customer_id');

        $staffCount = DB::table('users')
            ->where('is_active', true)
            ->count();

        return [
            'license_number' => config('cems.license_number', 'MSB-XXXXXXX'),
            'reporting_period' => $month,
            'report_date' => now()->format('Y-m-d'),
            'currencies' => $currencyData,
            'customer_count' => $customerCount,
            'staff_count' => $staffCount,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function generateFormLMCACsv(string $month): string
    {
        $data = $this->generateFormLMCA($month);
        $filename = "LMCA_{$month}.csv";
        $filepath = "reports/{$filename}";

        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');

        fputcsv($csv, ['BNM Form LMCA - Monthly Report']);
        fputcsv($csv, ['License Number', $data['license_number']]);
        fputcsv($csv, ['Reporting Period', $data['reporting_period']]);
        fputcsv($csv, ['Report Date', $data['report_date']]);
        fputcsv($csv, []);

        fputcsv($csv, [
            'Currency',
            'Buy Count',
            'Buy Volume (Foreign)',
            'Buy Value (MYR)',
            'Sell Count',
            'Sell Volume (Foreign)',
            'Sell Value (MYR)',
            'Opening Stock',
            'Closing Stock',
        ]);

        foreach ($data['currencies'] as $row) {
            fputcsv($csv, [
                $row['currency_code'],
                $row['buy_count'],
                $row['buy_volume'],
                $row['buy_value_myr'],
                $row['sell_count'],
                $row['sell_volume'],
                $row['sell_value_myr'],
                $row['opening_stock'],
                $row['closing_stock'],
            ]);
        }

        fputcsv($csv, []);
        fputcsv($csv, ['Total Customers Served', $data['customer_count']]);
        fputcsv($csv, ['Total Active Staff', $data['staff_count']]);

        fclose($csv);

        return $filepath;
    }

    public function generateQuarterlyLargeValueReport(string $quarter): array
    {
        [$year, $quarterNumber] = $this->parseQuarter($quarter);
        $startMonth = ($quarterNumber - 1) * 3 + 1;
        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->addMonths(3)->subDay();

        $transactions = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->with(['customer', 'user'])
            ->where('amount_local', '>=', self::LARGE_VALUE_THRESHOLD)
            ->orderBy('created_at')
            ->get();

        $monthlyBreakdown = [];
        for ($m = 0; $m < 3; $m++) {
            $monthDate = $startDate->copy()->addMonths($m);
            $monthTxns = $transactions->filter(function ($txn) use ($monthDate) {
                return $txn->created_at->format('Y-m') === $monthDate->format('Y-m');
            });

            $monthlyBreakdown[] = [
                'month' => $monthDate->format('Y-m'),
                'count' => $monthTxns->count(),
                'total_amount' => $monthTxns->sum('amount_local'),
            ];
        }

        $byCurrency = $transactions->groupBy('currency_code')->map(function ($txns) {
            return [
                'currency' => $txns->first()->currency_code,
                'count' => $txns->count(),
                'total_amount' => $txns->sum('amount_local'),
            ];
        })->values();

        return [
            'quarter' => $quarter,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('amount_local'),
            'monthly_breakdown' => $monthlyBreakdown,
            'by_currency' => $byCurrency,
            'data' => $transactions->map(function ($txn) {
                return [
                    'Transaction_ID' => 'TXN-'.str_pad($txn->id, 8, '0', STR_PAD_LEFT),
                    'Date' => $txn->created_at->format('Y-m-d'),
                    'Customer_Name' => $this->maskName($txn->customer->full_name),
                    'Amount_Local' => $txn->amount_local,
                    'Currency' => $txn->currency_code,
                    'Transaction_Type' => $txn->type,
                ];
            })->toArray(),
        ];
    }

    public function generateQuarterlyLargeValueCsv(string $quarter): string
    {
        $data = $this->generateQuarterlyLargeValueReport($quarter);
        $filename = "QLVR_{$quarter}.csv";
        $filepath = "reports/{$filename}";

        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');

        fputcsv($csv, ['BNM Quarterly Large Value Transaction Report']);
        fputcsv($csv, ['Quarter', $data['quarter']]);
        fputcsv($csv, ['Period', $data['period_start'].' to '.$data['period_end']]);
        fputcsv($csv, ['Total Transactions', $data['total_transactions']]);
        fputcsv($csv, ['Total Amount (MYR)', number_format($data['total_amount'], 2)]);
        fputcsv($csv, []);

        fputcsv($csv, ['Transaction_ID', 'Date', 'Customer_Name', 'Amount_Local', 'Currency', 'Transaction_Type']);

        foreach ($data['data'] as $row) {
            fputcsv($csv, array_values($row));
        }

        fclose($csv);

        return $filepath;
    }

    public function generatePositionLimitReport(): array
    {
        $positions = CurrencyPosition::with('currency')->get();
        $limits = config('cems.position_limits', []);

        $data = [];
        $totalExposure = '0';

        foreach ($positions as $position) {
            $limit = $limits[$position->currency_code] ?? null;
            $currentBalance = $position->quantity;
            if ($this->mathService->compare($currentBalance, '0') < 0) {
                $currentBalance = $this->mathService->multiply($currentBalance, '-1');
            }
            $limitValue = $limit ?? '0';
            $utilization = $this->mathService->compare($limitValue, '0') > 0
                ? $this->mathService->multiply(
                    $this->mathService->divide($currentBalance, $limitValue),
                    '100'
                )
                : '0';

            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'current_balance' => $position->quantity,
                'position_limit' => $limit,
                'utilization_percent' => $utilization,
                'average_cost' => $position->average_cost,
                'current_rate' => $position->current_rate,
                'exposure_myr' => $this->mathService->multiply($currentBalance, $position->current_rate ?? '0'),
                'status' => $this->mathService->compare($utilization, '90') >= 0
                    ? 'Critical'
                    : ($this->mathService->compare($utilization, '75') >= 0 ? 'Warning' : 'Normal'),
            ];

            $totalExposure = $this->mathService->add(
                $totalExposure,
                $this->mathService->multiply($currentBalance, $position->current_rate ?? '0')
            );
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'total_exposure_myr' => $totalExposure,
            'positions' => $data,
            'summary' => [
                'total_currencies' => count($data),
                'currencies_at_warning' => collect($data)->where('status', 'Warning')->count(),
                'currencies_at_critical' => collect($data)->where('status', 'Critical')->count(),
            ],
        ];
    }

    public function generatePositionLimitCsv(): string
    {
        $data = $this->generatePositionLimitReport();
        $filename = 'PositionLimit_'.now()->format('Y-m-d').'.csv';
        $filepath = "reports/{$filename}";

        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');

        fputcsv($csv, ['BNM Position Limit Utilization Report']);
        fputcsv($csv, ['Generated', $data['generated_at']]);
        fputcsv($csv, ['Total Exposure (MYR)', $data['total_exposure_myr']]);
        fputcsv($csv, []);

        fputcsv($csv, [
            'Currency',
            'Current Balance',
            'Position Limit',
            'Utilization %',
            'Avg Cost Rate',
            'Last Valuation Rate',
            'Exposure (MYR)',
            'Status',
        ]);

        foreach ($data['positions'] as $row) {
            fputcsv($csv, [
                $row['currency_code'],
                $row['current_balance'],
                $row['position_limit'] ?? 'N/A',
                $row['utilization_percent'].'%',
                $row['average_cost'],
                $row['current_rate'],
                $row['exposure_myr'],
                $row['status'],
            ]);
        }

        fclose($csv);

        return $filepath;
    }

    private function parseQuarter(string $quarter): array
    {
        if (! preg_match('/^(\d{4})-Q([1-4])$/', $quarter, $matches)) {
            throw new \InvalidArgumentException("Invalid quarter format: {$quarter}. Expected YYYY-QN.");
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
