<?php

namespace App\Services;

use App\Exceptions\Domain\ClosedPeriodException;
use App\Exceptions\Domain\UnbalancedJournalEntriesException;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PeriodCloseService
{
    /**
     * Accounting service for journal entry operations.
     */
    protected AccountingService $accountingService;

    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Create a new PeriodCloseService instance.
     *
     * @param  AccountingService  $accountingService  Service for journal entry operations
     * @param  MathService  $mathService  Service for high-precision calculations
     */
    public function __construct(AccountingService $accountingService, MathService $mathService)
    {
        $this->accountingService = $accountingService;
        $this->mathService = $mathService;
    }

    /**
     * Close an accounting period
     *
     * Validates all entries are balanced, creates closing entries for revenue/expense accounts,
     * updates the period status, and logs the action.
     *
     * @param  AccountingPeriod  $period  The accounting period to close
     * @param  int  $closedBy  ID of the user closing the period
     * @return array Result array containing 'success', 'period', and 'closing_entries'
     *
     * @throws Exception If period is already closed or unbalanced entries are found
     */
    public function closePeriod(AccountingPeriod $period, int $closedBy): array
    {
        if ($period->isClosed()) {
            throw new ClosedPeriodException($period->period_code);
        }

        return DB::transaction(function () use ($period, $closedBy) {
            // Step 1: Validate all entries are balanced
            $this->validatePeriodBalances($period);

            // Step 2: Create closing entries for revenue/expense accounts
            $closingEntries = $this->createClosingEntries($period, $closedBy);

            // Step 3: Update period status
            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $closedBy,
            ]);

            // Step 4: Log the action
            SystemLog::create([
                'user_id' => $closedBy,
                'action' => 'period_closed',
                'entity_type' => 'AccountingPeriod',
                'entity_id' => $period->id,
                'new_values' => [
                    'period_code' => $period->period_code,
                    'closed_at' => now()->toDateTimeString(),
                ],
                'severity' => 'INFO',
                'ip_address' => request()->ip(),
            ]);

            return [
                'success' => true,
                'period' => $period,
                'closing_entries' => $closingEntries,
            ];
        });
    }

    /**
     * Validate all journal entries in period are balanced
     *
     * @param  AccountingPeriod  $period  The accounting period to validate
     *
     * @throws Exception If unbalanced journal entries are found
     */
    protected function validatePeriodBalances(AccountingPeriod $period): void
    {
        $unbalanced = JournalEntry::where('period_id', $period->id)
            ->where('status', 'Posted')
            ->get()
            ->filter(fn ($entry) => ! $entry->isBalanced());

        if ($unbalanced->isNotEmpty()) {
            $ids = $unbalanced->pluck('id')->join(', ');
            throw new UnbalancedJournalEntriesException($ids);
        }
    }

    /**
     * Create closing entries to transfer revenue/expense to retained earnings
     *
     * Calculates total revenue and expenses for the period, then creates
     * a journal entry to transfer the net income to retained earnings.
     *
     * @param  AccountingPeriod  $period  The accounting period being closed
     * @param  int  $closedBy  ID of the user creating the closing entries
     * @return array Array of created closing journal entries
     */
    protected function createClosingEntries(AccountingPeriod $period, int $closedBy): array
    {
        $entries = [];

        // Get revenue accounts
        $revenues = ChartOfAccount::where('account_type', 'Revenue')->get();
        $totalRevenue = '0';
        foreach ($revenues as $account) {
            $balance = $this->accountingService->getAccountBalance(
                $account->account_code,
                $period->end_date->toDateString()
            );
            $totalRevenue = $this->mathService->add($totalRevenue, $balance);
        }

        // Get expense accounts
        $expenses = ChartOfAccount::where('account_type', 'Expense')->get();
        $totalExpenses = '0';
        foreach ($expenses as $account) {
            $balance = $this->accountingService->getAccountBalance(
                $account->account_code,
                $period->end_date->toDateString()
            );
            $totalExpenses = $this->mathService->add($totalExpenses, $balance);
        }

        // Calculate net income
        $netIncome = $this->mathService->subtract($totalRevenue, $totalExpenses);

        // Only create entry if there's activity
        if ($this->mathService->compare($netIncome, '0') !== 0) {
            // Validate and get configured account codes
            $revenueSummaryAccount = $this->getValidatedAccountCode('accounting.revenue_summary_account', '4000');
            $expenseSummaryAccount = $this->getValidatedAccountCode('accounting.expense_summary_account', '5000');
            $retainedEarningsAccount = $this->getValidatedAccountCode('accounting.retained_earnings_account', '3100');

            $entry = $this->accountingService->createJournalEntry(
                [
                    [
                        'account_code' => $revenueSummaryAccount,
                        'debit' => $totalRevenue,
                        'credit' => 0,
                    ],
                    [
                        'account_code' => $expenseSummaryAccount,
                        'debit' => 0,
                        'credit' => $totalExpenses,
                    ],
                    [
                        'account_code' => $retainedEarningsAccount,
                        'debit' => $this->mathService->compare($netIncome, '0') < 0 ? $this->mathService->multiply($netIncome, '-1') : 0,
                        'credit' => $this->mathService->compare($netIncome, '0') > 0 ? $netIncome : 0,
                    ],
                ],
                'Period_Close',
                $period->id,
                "Period close for {$period->period_code} - Net Income: RM {$netIncome}",
                $period->end_date->toDateString(),
                $closedBy
            );

            // Update the entry with period_id
            $entry->update(['period_id' => $period->id]);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Get validated account code from config
     *
     * Retrieves account code from configuration and validates it exists
     * and is active in the chart of accounts when validation is enabled.
     *
     * @param  string  $configKey  Configuration key for the account code
     * @param  string  $defaultCode  Default account code to use if config not set
     * @return string The validated account code
     *
     * @throws \InvalidArgumentException If account doesn't exist or is inactive (when validation is enabled)
     */
    protected function getValidatedAccountCode(string $configKey, string $defaultCode): string
    {
        $code = Config::get($configKey, $defaultCode);

        if (Config::get('accounting.validate_accounts', true)) {
            $account = ChartOfAccount::where('account_code', $code)->first();

            if (! $account) {
                throw new \InvalidArgumentException("Configured account '{$configKey}' with code '{$code}' does not exist in chart of accounts");
            }

            if (! $account->is_active) {
                throw new \InvalidArgumentException("Configured account '{$configKey}' with code '{$code}' is not active");
            }
        }

        return $code;
    }
}
