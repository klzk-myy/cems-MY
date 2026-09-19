<?php

namespace App\Services\Reporting\Generators;

use App\Services\Accounting\LedgerService;
use App\Services\Reporting\CsvReportWriter;
use Carbon\Carbon;

/**
 * CSV exports backed by LedgerService — the same proven implementations
 * behind the accounting reports screen and fiscal-year close, so these paths
 * cannot drift from the scheduled ledger output.
 */
class LedgerBackedReportGenerator
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    public function generateTrialBalance(string $period): string
    {
        $asOf = Carbon::parse($period)->endOfMonth()->toDateString();
        $trialBalance = $this->ledgerService->getTrialBalance($asOf);

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

        return $this->csvReportWriter->writeWithTitleRows(
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

    public function generateMonthEnd(string $period): string
    {
        return $this->csvReportWriter->write(
            "MonthEnd_{$period}.csv",
            ['Period', 'Generated'],
            [[$period, now()->toDateTimeString()]]
        );
    }

    public function generateProfitLoss(string $period): string
    {
        $date = Carbon::parse($period);
        $from = $date->copy()->startOfMonth()->toDateString();
        $to = $date->copy()->endOfMonth()->toDateString();

        $pnl = $this->ledgerService->getProfitAndLoss($from, $to);

        $rows = [];
        foreach ($pnl['revenues'] as $revenue) {
            $rows[] = ['Revenue', $revenue['account_code'], $revenue['account_name'], $revenue['amount_myr']];
        }
        $rows[] = ['Total Revenue', '', '', $pnl['total_revenue']];

        foreach ($pnl['expenses'] as $expense) {
            $rows[] = ['Expense', $expense['account_code'], $expense['account_name'], $expense['amount_myr']];
        }
        $rows[] = ['Total Expenses', '', '', $pnl['total_expenses']];
        $rows[] = ['Net Profit', '', '', $pnl['net_profit']];

        return $this->csvReportWriter->writeWithTitleRows(
            "ProfitLoss_{$period}.csv",
            [['Profit & Loss Report'], ['Period', "{$from} to {$to}"]],
            ['Category', 'Account Code', 'Account Name', 'Amount (MYR)'],
            $rows
        );
    }

    public function generateBalanceSheet(string $period): string
    {
        $asOf = Carbon::parse($period)->endOfMonth()->toDateString();
        $balanceSheet = $this->ledgerService->getBalanceSheet($asOf);

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

        return $this->csvReportWriter->writeWithTitleRows(
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
}
