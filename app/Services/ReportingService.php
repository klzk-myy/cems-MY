<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportingService
{
    protected EncryptionService $encryptionService;

    protected MathService $mathService;

    protected ThresholdService $thresholdService;

    public function __construct(
        EncryptionService $encryptionService,
        MathService $mathService,
        ThresholdService $thresholdService
    ) {
        $this->encryptionService = $encryptionService;
        $this->mathService = $mathService;
        $this->thresholdService = $thresholdService;
    }

    public function generateLCTR(string $month): string
    {
        $startDate = now()->parse($month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $transactions = Transaction::where('amount_local', '>=', $this->thresholdService->getCtrThreshold())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'user'])
            ->get();

        $filename = "LCTR_{$month}.csv";
        $filepath = "reports/{$filename}";

        // Ensure the reports directory exists
        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');

        // Headers per BNM format
        fputcsv($csv, [
            'Transaction_ID',
            'Date',
            'Time',
            'Customer_ID',
            'Customer_Name',
            'ID_Type',
            'Amount_Local',
            'Amount_Foreign',
            'Currency',
            'Transaction_Type',
            'Branch_ID',
            'Teller_ID',
        ]);

        foreach ($transactions as $txn) {
            fputcsv($csv, [
                $txn->id,
                $txn->created_at->format('Y-m-d'),
                $txn->created_at->format('H:i:s'),
                $txn->customer_id,
                $this->maskName($txn->customer->full_name),
                $txn->customer->id_type,
                $txn->amount_local,
                $txn->amount_foreign,
                $txn->currency_code,
                $txn->type,
                $txn->till_id ?? 'MAIN',
                $txn->user_id,
            ]);
        }

        fclose($csv);

        return $filepath;
    }

    public function generateMSB2(string $date): string
    {
        $queryDate = now()->parse($date);

        $summary = DB::table('transactions')
            ->select(
                'currency_code',
                DB::raw("SUM(CASE WHEN type = 'Buy' THEN amount_foreign ELSE 0 END) as buy_volume"),
                DB::raw("SUM(CASE WHEN type = 'Buy' THEN 1 ELSE 0 END) as buy_count"),
                DB::raw("SUM(CASE WHEN type = 'Sell' THEN amount_foreign ELSE 0 END) as sell_volume"),
                DB::raw("SUM(CASE WHEN type = 'Sell' THEN 1 ELSE 0 END) as sell_count")
            )
            ->whereDate('created_at', $queryDate)
            ->groupBy('currency_code')
            ->get();

        $filename = "MSB2_{$date}.csv";
        $filepath = "reports/{$filename}";

        // Ensure the reports directory exists
        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $csv = fopen(Storage::path($filepath), 'w');

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
                $row->buy_volume,
                $row->buy_count,
                $row->sell_volume,
                $row->sell_count,
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

    public function generateLCTRData(string $month): array
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $transactions = Transaction::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('amount_local', '>=', $this->thresholdService->getCtrThreshold())
            ->where('status', 'Completed')
            ->orderBy('created_at')
            ->get();

        $rows = [];
        foreach ($transactions as $txn) {
            $idNumber = $txn->customer->id_number_encrypted
                ? $this->encryptionService->decrypt($txn->customer->id_number_encrypted)
                : 'N/A';

            $rows[] = [
                'Transaction_ID' => 'TXN-'.str_pad($txn->id, 8, '0', STR_PAD_LEFT),
                'Transaction_Date' => $txn->created_at->format('Y-m-d'),
                'Transaction_Time' => $txn->created_at->format('H:i:s'),
                'Customer_ID_Type' => $txn->customer->id_type,
                'Customer_ID_Number' => $idNumber,
                'Customer_Name' => $txn->customer->full_name,
                'Customer_Nationality' => $txn->customer->nationality,
                'Transaction_Type' => $txn->type,
                'Currency_Code' => $txn->currency_code,
                'Amount_Local' => $txn->amount_local,
                'Amount_Foreign' => $txn->amount_foreign,
                'Exchange_Rate' => $txn->rate,
                'Till_ID' => $txn->till_id ?? 'MAIN',
                'Teller_ID' => 'USR-'.str_pad($txn->user_id, 6, '0', STR_PAD_LEFT),
                'Purpose' => $txn->purpose,
                'Source_of_Funds' => $txn->source_of_funds,
                'CDD_Level' => $txn->cdd_level,
                'Status' => $txn->status,
            ];
        }

        return [
            'month' => $month,
            'generated_at' => now()->toIso8601String(),
            'total_transactions' => count($rows),
            'total_amount' => $transactions->sum('amount_local'),
            'data' => $rows,
        ];
    }

    public function generateMSB2Data(string $date): array
    {
        $transactions = Transaction::whereDate('created_at', $date)
            ->where('status', 'Completed')
            ->get();

        $currencies = Currency::where('is_active', true)->get();
        $rows = [];

        foreach ($currencies as $currency) {
            $buyTxns = $transactions->where('currency_code', $currency->code)->where('type', 'Buy');
            $sellTxns = $transactions->where('currency_code', $currency->code)->where('type', 'Sell');
            $position = CurrencyPosition::where('currency_code', $currency->code)->first();

            $rows[] = [
                'Date' => $date,
                'Currency' => $currency->code,
                'Buy_Volume_MYR' => (string) $buyTxns->sum('amount_local'),
                'Buy_Count' => $buyTxns->count(),
                'Sell_Volume_MYR' => (string) $sellTxns->sum('amount_local'),
                'Sell_Count' => $sellTxns->count(),
                'Avg_Buy_Rate' => (string) ($buyTxns->avg('rate') ?? '0'),
                'Avg_Sell_Rate' => (string) ($sellTxns->avg('rate') ?? '0'),
                'Opening_Position' => $position ? $position->balance : '0',
                'Closing_Position' => $position ? $position->balance : '0',
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
                'balance' => $position->balance,
                'avg_cost_rate' => $position->avg_cost_rate,
                'last_valuation_rate' => $position->last_valuation_rate,
                'unrealized_pnl' => $position->unrealized_pnl,
            ];
            $totalUnrealizedPnl = $this->mathService->add($totalUnrealizedPnl, $position->unrealized_pnl ?? '0');
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
            ->whereRaw('unrealized_pnl != 0')
            ->get();

        $data = [];
        $totalGain = '0';
        $totalLoss = '0';

        foreach ($positions as $position) {
            $pnl = $position->unrealized_pnl ?? '0';

            if ($this->mathService->compare($pnl, '0') >= 0) {
                $totalGain = $this->mathService->add($totalGain, $pnl);
            } else {
                $totalLoss = $this->mathService->add($totalLoss, $pnl);
            }

            $data[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'balance' => $position->balance,
                'avg_cost_rate' => $position->avg_cost_rate,
                'last_valuation_rate' => $position->last_valuation_rate,
                'unrealized_pnl' => $pnl,
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
        $currencyData = [];

        foreach ($currencies as $currency) {
            $buyTxns = Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('currency_code', $currency->code)
                ->where('type', 'Buy')
                ->where('status', 'Completed')
                ->get();

            $sellTxns = Transaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('currency_code', $currency->code)
                ->where('type', 'Sell')
                ->where('status', 'Completed')
                ->get();

            $openingPosition = CurrencyPosition::where('currency_code', $currency->code)
                ->first();
            $closingPosition = $openingPosition;

            $currencyData[] = [
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'buy_count' => $buyTxns->count(),
                'buy_volume' => $buyTxns->sum('amount_foreign'),
                'buy_value_myr' => $buyTxns->sum('amount_local'),
                'sell_count' => $sellTxns->count(),
                'sell_volume' => $sellTxns->sum('amount_foreign'),
                'sell_value_myr' => $sellTxns->sum('amount_local'),
                'opening_stock' => $openingPosition ? $openingPosition->balance : '0',
                'closing_stock' => $closingPosition ? $closingPosition->balance : '0',
            ];
        }

        $customerCount = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'Completed')
            ->distinct('customer_id')
            ->count('customer_id');

        $staffCount = DB::table('users')
            ->where('is_active', true)
            ->count();

        return [
            'license_number' => config('app.license_number', 'MSB-XXXXXXX'),
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
        $parts = explode('-', $quarter);
        $year = (int) $parts[0];
        $q = (int) substr($parts[1], 1);

        $startMonth = (($q - 1) * 3) + 1;
        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->addMonths(3)->subDay();

        $transactions = Transaction::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('amount_local', '>=', $this->thresholdService->getCtrThreshold())
            ->where('status', 'Completed')
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
            $currentBalance = $position->balance;
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
                'current_balance' => $position->balance,
                'position_limit' => $limit,
                'utilization_percent' => $utilization,
                'avg_cost_rate' => $position->avg_cost_rate,
                'last_valuation_rate' => $position->last_valuation_rate,
                'exposure_myr' => $this->mathService->multiply($currentBalance, $position->last_valuation_rate ?? '0'),
                'status' => $this->mathService->compare($utilization, '90') >= 0
                    ? 'Critical'
                    : ($this->mathService->compare($utilization, '75') >= 0 ? 'Warning' : 'Normal'),
            ];

            $totalExposure = $this->mathService->add(
                $totalExposure,
                $this->mathService->multiply($currentBalance, $position->last_valuation_rate ?? '0')
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
                $row['avg_cost_rate'],
                $row['last_valuation_rate'],
                $row['exposure_myr'],
                $row['status'],
            ]);
        }

        fclose($csv);

        return $filepath;
    }

    /**
     * Generate a report based on type and date.
     *
     * @param  string  $type  Report type (e.g., 'msb2', 'lctr')
     * @param  string  $date  Date string (YYYY-MM-DD or month)
     * @return string Filepath of generated report
     *
     * @throws \InvalidArgumentException
     */
    public function generateReport(string $type, string $date): string
    {
        return match ($type) {
            'msb2' => $this->generateMSB2($date),
            'lctr' => $this->generateLCTR($date),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };
    }
}
