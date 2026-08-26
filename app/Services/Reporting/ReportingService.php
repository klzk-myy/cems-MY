<?php

namespace App\Services\Reporting;

use App\Enums\ReportType;
use App\Enums\TransactionType;
use App\Exceptions\Domain\ReportValidationException;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\ReportGenerated;
use App\Models\User;
use App\Services\Accounting\LedgerService;
use App\Services\Contracts\ReportingServiceInterface;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\ValueObjects\Quarter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportingService implements ReportingServiceInterface
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService
    ) {}

    public function recordGeneratedReport(
        ReportType $reportType,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $status = 'Generated',
        string $format = 'CSV'
    ): ReportGenerated {
        return ReportGenerated::create([
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => auth()->id() ?? config('cems.system_user_id', 1),
            'generated_at' => now(),
            'file_format' => $format,
            'status' => $status,
        ]);
    }

    public function generateMSB2(string $date): string
    {
        $query = app(TransactionReportQuery::class);

        $summary = $query->buySellSummary(
            $query->completed()
                ->forDateRange($date, $date)
                ->select('currency_code')
                ->orderBy('currency_code'),
            'currency_code',
            'amount_foreign'
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

        return app(CsvReportWriter::class)->write($filename, $headers, $rows);
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

    /**
     * Average a collection of DECIMAL rate strings without float precision loss.
     *
     * Rates are stored at decimal(18,6), so the average keeps 6 decimals.
     */
    private function averageRate(Collection $txns): string
    {
        $count = $txns->count();

        if ($count === 0) {
            return '0';
        }

        $total = '0';
        foreach ($txns as $txn) {
            $total = $this->mathService->add($total, (string) $txn->rate);
        }

        return bcdiv($total, (string) $count, 6);
    }

    public function generateMSB2Data(string $date): array
    {
        $query = app(TransactionReportQuery::class);

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

        $transactionQuery = app(TransactionReportQuery::class);

        $allTxns = $transactionQuery
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
            $myrVolumes = $transactionQuery->buySellVolumes($currencyTxns);
            $foreignVolumes = $transactionQuery->buySellVolumes($currencyTxns, 'amount_foreign');

            $openingPosition = $positions->get($currency->code);

            // No historical position snapshots exist, so the opening stock is
            // reconstructed backwards from today's live position. Sign
            // convention mirrors CurrencyPositionService::updatePosition:
            // a Buy adds amount_foreign to the position, a Sell subtracts it,
            // hence closing = opening + buys - sells.
            $closingStock = $openingPosition ? (string) $openingPosition->quantity : '0';
            $netMovement = $this->mathService->subtract(
                (string) $foreignVolumes['buy_volume'],
                (string) $foreignVolumes['sell_volume']
            );
            $openingStock = $this->mathService->subtract($closingStock, $netMovement);

            $currencyData[] = [
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'buy_count' => $myrVolumes['buy_count'],
                'buy_volume' => $foreignVolumes['buy_volume'],
                'buy_value_myr' => $myrVolumes['buy_volume'],
                'sell_count' => $myrVolumes['sell_count'],
                'sell_volume' => $foreignVolumes['sell_volume'],
                'sell_value_myr' => $myrVolumes['sell_volume'],
                'opening_stock' => $openingStock,
                'closing_stock' => $closingStock,
            ];
        }

        $customerCount = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->distinct('customer_id')
            ->count('customer_id');

        $staffCount = User::query()
            ->where('is_active', true)
            ->count();

        $licenseNumber = config('cems.license_number');
        if (empty($licenseNumber)) {
            throw new ReportValidationException('BNM license number is not configured. Set BNM_LICENSE_NUMBER in .env.');
        }

        return [
            'license_number' => $licenseNumber,
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

        $titleRows = [
            ['BNM Form LMCA - Monthly Report'],
            ['License Number', $data['license_number']],
            ['Reporting Period', $data['reporting_period']],
            ['Report Date', $data['report_date']],
        ];

        $headers = [
            'Currency',
            'Buy Count',
            'Buy Volume (Foreign)',
            'Buy Value (MYR)',
            'Sell Count',
            'Sell Volume (Foreign)',
            'Sell Value (MYR)',
            'Opening Stock',
            'Closing Stock',
        ];

        $rows = [];
        foreach ($data['currencies'] as $row) {
            $rows[] = [
                $row['currency_code'],
                $row['buy_count'],
                $row['buy_volume'],
                $row['buy_value_myr'],
                $row['sell_count'],
                $row['sell_volume'],
                $row['sell_value_myr'],
                $row['opening_stock'],
                $row['closing_stock'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Total Customers Served', $data['customer_count']];
        $rows[] = ['Total Active Staff', $data['staff_count']];

        return app(CsvReportWriter::class)->writeWithTitleRows($filename, $titleRows, $headers, $rows);
    }

    public function generateQuarterlyLargeValueReport(string $quarter): array
    {
        $quarterVo = Quarter::fromString($quarter);
        $startDate = $quarterVo->startDate();
        $endDate = $quarterVo->endDate();

        $transactions = app(TransactionReportQuery::class)
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

    public function generateQuarterlyLargeValueCsv(string $quarter): string
    {
        $data = $this->generateQuarterlyLargeValueReport($quarter);
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

        return app(CsvReportWriter::class)->writeWithTitleRows($filename, $titleRows, $headers, $rows);
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

        $titleRows = [
            ['BNM Position Limit Utilization Report'],
            ['Generated', $data['generated_at']],
            ['Total Exposure (MYR)', $data['total_exposure_myr']],
        ];

        $headers = [
            'Currency',
            'Current Balance',
            'Position Limit',
            'Utilization %',
            'Avg Cost Rate',
            'Last Valuation Rate',
            'Exposure (MYR)',
            'Status',
        ];

        $rows = [];
        foreach ($data['positions'] as $row) {
            $rows[] = [
                $row['currency_code'],
                $row['current_balance'],
                $row['position_limit'] ?? 'N/A',
                $row['utilization_percent'].'%',
                $row['average_cost'],
                $row['current_rate'],
                $row['exposure_myr'],
                $row['status'],
            ];
        }

        return app(CsvReportWriter::class)->writeWithTitleRows($filename, $titleRows, $headers, $rows);
    }

    public function generateReport(string $reportType, string $period): string
    {
        $type = ReportType::tryFrom($reportType);

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }

        return match ($type) {
            ReportType::Msb2 => $this->generateMSB2($period),
            ReportType::Lmca => $this->generateFormLMCACsv(Carbon::parse($period)->format('Y-m')),
            ReportType::Qlvr => $this->generateQuarterlyLargeValueCsv($this->periodToQuarter($period)),
            ReportType::Plr => $this->generatePositionLimitCsv(),
            ReportType::TrialBalance => $this->generateTrialBalance($period),
            ReportType::MonthEnd => $this->generateMonthEnd($period),
            ReportType::ProfitLoss => $this->generateProfitLoss($period),
            ReportType::BalanceSheet => $this->generateBalanceSheet($period),
        };
    }

    protected function generateTrialBalance(string $period): string
    {
        // Delegate to LedgerService - the same proven implementation behind the
        // accounting reports screen and fiscal-year close - so this path cannot
        // drift from the scheduled trial balance output.
        $asOf = Carbon::parse($period)->endOfMonth()->toDateString();
        $trialBalance = app(LedgerService::class)->getTrialBalance($asOf);

        $rows = [];
        foreach ($trialBalance['accounts'] as $account) {
            $rows[] = [
                $account['account_code'],
                $account['account_name'],
                $account['debit'],
                $account['credit'],
            ];
        }

        $rows[] = ['', 'TOTAL', $trialBalance['total_debits'], $trialBalance['total_credits']];

        return app(CsvReportWriter::class)->writeWithTitleRows(
            "TrialBalance_{$period}.csv",
            [
                ['Trial Balance Report'],
                ['As of', $asOf],
                ['Balanced', $trialBalance['is_balanced'] ? 'Yes' : 'No'],
            ],
            ['Account Code', 'Account Name', 'Debit', 'Credit'],
            $rows
        );
    }

    protected function generateMonthEnd(string $period): string
    {
        return app(CsvReportWriter::class)->write(
            "MonthEnd_{$period}.csv",
            ['Period', 'Generated'],
            [[$period, now()->toDateTimeString()]]
        );
    }

    protected function generateProfitLoss(string $period): string
    {
        // Delegate to LedgerService::getProfitAndLoss so the P&L reflects real
        // ledger activity instead of placeholder account codes.
        $date = Carbon::parse($period);
        $from = $date->copy()->startOfMonth()->toDateString();
        $to = $date->copy()->endOfMonth()->toDateString();

        $pnl = app(LedgerService::class)->getProfitAndLoss($from, $to);

        $rows = [];
        foreach ($pnl['revenues'] as $revenue) {
            $rows[] = ['Revenue', $revenue['account_code'], $revenue['account_name'], $revenue['amount']];
        }
        $rows[] = ['Total Revenue', '', '', $pnl['total_revenue']];

        foreach ($pnl['expenses'] as $expense) {
            $rows[] = ['Expense', $expense['account_code'], $expense['account_name'], $expense['amount']];
        }
        $rows[] = ['Total Expenses', '', '', $pnl['total_expenses']];
        $rows[] = ['Net Profit', '', '', $pnl['net_profit']];

        return app(CsvReportWriter::class)->writeWithTitleRows(
            "ProfitLoss_{$period}.csv",
            [['Profit & Loss Report'], ['Period', "{$from} to {$to}"]],
            ['Category', 'Account Code', 'Account Name', 'Amount (MYR)'],
            $rows
        );
    }

    protected function generateBalanceSheet(string $period): string
    {
        // Delegate to LedgerService::getBalanceSheet so assets/liabilities come
        // from real ledger balances instead of placeholder account codes.
        $asOf = Carbon::parse($period)->endOfMonth()->toDateString();
        $balanceSheet = app(LedgerService::class)->getBalanceSheet($asOf);

        $rows = [];
        foreach ($balanceSheet['assets'] as $asset) {
            $rows[] = ['Asset', $asset['account_code'], $asset['account_name'], $asset['balance']];
        }
        $rows[] = ['Total Assets', '', '', $balanceSheet['total_assets']];

        foreach ($balanceSheet['liabilities'] as $liability) {
            $rows[] = ['Liability', $liability['account_code'], $liability['account_name'], $liability['balance']];
        }
        $rows[] = ['Total Liabilities', '', '', $balanceSheet['total_liabilities']];

        foreach ($balanceSheet['equity'] as $equity) {
            $rows[] = ['Equity', $equity['account_code'], $equity['account_name'], $equity['balance']];
        }
        $rows[] = ['Total Equity', '', '', $balanceSheet['total_equity']];

        return app(CsvReportWriter::class)->writeWithTitleRows(
            "BalanceSheet_{$period}.csv",
            [
                ['Balance Sheet Report'],
                ['As of', $asOf],
                ['Balanced', $balanceSheet['is_balanced'] ? 'Yes' : 'No'],
            ],
            ['Category', 'Account Code', 'Account Name', 'Amount (MYR)'],
            $rows
        );
    }

    private function periodToQuarter(string $period): string
    {
        $date = Carbon::parse($period);

        return 'Q'.$date->quarter.'-'.$date->year;
    }
}
