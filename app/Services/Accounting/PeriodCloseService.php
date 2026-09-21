<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountMappingKey;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Exceptions\Domain\ClosedPeriodException;
use App\Exceptions\Domain\UnbalancedJournalEntriesException;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AuditService;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
     * Audit service for action logging.
     */
    protected AuditService $auditService;

    /**
     * Account mapping service for resolving closing accounts.
     */
    protected AccountMappingService $accountMappingService;

    /**
     * Create a new PeriodCloseService instance.
     *
     * @param  AccountingService  $accountingService  Service for journal entry operations
     * @param  MathService  $mathService  Service for high-precision calculations
     * @param  AuditService  $auditService  Service for action logging
     * @param  AccountMappingService  $accountMappingService  Service for resolving closing accounts
     */
    public function __construct(
        AccountingService $accountingService,
        MathService $mathService,
        AuditService $auditService,
        AccountMappingService $accountMappingService,
    ) {
        $this->accountingService = $accountingService;
        $this->mathService = $mathService;
        $this->auditService = $auditService;
        $this->accountMappingService = $accountMappingService;
    }

    /**
     * Close an accounting period
     *
     * Validates all entries are balanced, creates closing entries for revenue/expense accounts,
     * updates the period status, and logs the action.
     *
     * @param  AccountingPeriod  $period  The accounting period to close
     * @param  int|null  $closedBy  ID of the user closing the period (null for system-initiated closes)
     * @return array Result array containing 'success', 'period', and 'closing_entries'
     *
     * @throws Exception If period is already closed or unbalanced entries are found
     */
    public function closePeriod(AccountingPeriod $period, ?int $closedBy = null): array
    {
        if ($period->isClosed()) {
            throw new ClosedPeriodException($period->period_code);
        }

        return DB::transaction(function () use ($period, $closedBy) {
            // Re-read under a pessimistic lock so concurrent closes serialise;
            // the unlocked isClosed() guard above is only a fast path.
            $lockedPeriod = AccountingPeriod::where('id', $period->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPeriod || $lockedPeriod->isClosed()) {
                throw new ClosedPeriodException($period->period_code);
            }

            // Step 1: Validate all entries are balanced
            $this->validatePeriodBalances($lockedPeriod);

            // Step 2: Create closing entries for revenue/expense accounts
            $closingEntries = $this->createClosingEntries($lockedPeriod, $closedBy);

            // Step 3: Update period status
            $lockedPeriod->update([
                'status' => AccountingPeriodStatus::Closed->value,
                'closed_at' => now(),
                'closed_by' => $closedBy,
            ]);

            // Step 4: Log the action
            $this->auditService->log(
                'period_closed',
                $closedBy,
                'AccountingPeriod',
                $lockedPeriod->id,
                [],
                [
                    'period_code' => $lockedPeriod->period_code,
                    'closed_at' => now()->toDateTimeString(),
                ]
            );

            return [
                'success' => true,
                'period' => $lockedPeriod,
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
        // Eager-load lines: isBalanced() iterates $entry->lines, so without
        // with('lines') every entry triggers an extra query (N+1).
        $unbalanced = JournalEntry::with('lines')
            ->where('period_id', $period->id)
            ->where('status', JournalEntryStatus::Posted->value)
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
     * @param  int|null  $closedBy  ID of the user creating the closing entries
     * @return array Array of created closing journal entries
     */
    protected function createClosingEntries(AccountingPeriod $period, ?int $closedBy): array
    {
        $entries = [];
        $asOfDate = $period->end_date->toDateString();

        $revenueSummaryAccount = $this->accountMappingService->code(AccountMappingKey::CloseRevenueSummary);
        $expenseSummaryAccount = $this->accountMappingService->code(AccountMappingKey::CloseExpenseSummary);
        $retainedEarningsAccount = $this->accountMappingService->code(AccountMappingKey::CloseRetainedEarnings);

        $revenues = ChartOfAccount::where('account_type', AccountType::Revenue->value)->get();
        $expenses = ChartOfAccount::where('account_type', AccountType::Expense->value)->get();

        $revenueBalances = $this->getNetBalances($revenues->pluck('account_code')->toArray(), $asOfDate);
        $expenseBalances = $this->getNetBalances($expenses->pluck('account_code')->toArray(), $asOfDate);

        $closingLines = [];
        $totalRevenue = '0';
        $totalExpenses = '0';

        foreach ($revenues as $account) {
            // Natural balance for a revenue account is credits minus debits.
            $balance = $this->mathService->multiply($revenueBalances[$account->account_code] ?? '0', '-1');
            if ($this->mathService->compare($balance, '0') === 0) {
                continue;
            }

            $totalRevenue = $this->mathService->add($totalRevenue, $balance);
            if ($this->mathService->compare($balance, '0') > 0) {
                // Debit revenue account to zero it
                $closingLines[] = ['account_code' => $account->account_code, 'debit' => $balance, 'credit' => 0];
            } else {
                // Contra-revenue (net debit balance): close with a credit
                $closingLines[] = ['account_code' => $account->account_code, 'debit' => 0, 'credit' => $this->mathService->multiply($balance, '-1')];
            }
        }

        if ($this->mathService->compare($totalRevenue, '0') > 0) {
            $closingLines[] = ['account_code' => $revenueSummaryAccount, 'debit' => 0, 'credit' => $totalRevenue];
        } elseif ($this->mathService->compare($totalRevenue, '0') < 0) {
            $closingLines[] = ['account_code' => $revenueSummaryAccount, 'debit' => $this->mathService->multiply($totalRevenue, '-1'), 'credit' => 0];
        }

        foreach ($expenses as $account) {
            $balance = $expenseBalances[$account->account_code] ?? '0';
            if ($this->mathService->compare($balance, '0') === 0) {
                continue;
            }

            $totalExpenses = $this->mathService->add($totalExpenses, $balance);
            if ($this->mathService->compare($balance, '0') > 0) {
                // Credit expense account to zero it
                $closingLines[] = ['account_code' => $account->account_code, 'debit' => 0, 'credit' => $balance];
            } else {
                // Contra-expense (net credit balance): close with a debit
                $closingLines[] = ['account_code' => $account->account_code, 'debit' => $this->mathService->multiply($balance, '-1'), 'credit' => 0];
            }
        }

        if ($this->mathService->compare($totalExpenses, '0') > 0) {
            $closingLines[] = ['account_code' => $expenseSummaryAccount, 'debit' => $totalExpenses, 'credit' => 0];
        } elseif ($this->mathService->compare($totalExpenses, '0') < 0) {
            $closingLines[] = ['account_code' => $expenseSummaryAccount, 'debit' => 0, 'credit' => $this->mathService->multiply($totalExpenses, '-1')];
        }

        $netIncome = $this->mathService->subtract($totalRevenue, $totalExpenses);

        if ($this->mathService->compare($netIncome, '0') !== 0) {
            if ($this->mathService->compare($netIncome, '0') > 0) {
                // Profit: debit revenue summary, credit retained earnings
                $closingLines[] = ['account_code' => $revenueSummaryAccount, 'debit' => $netIncome, 'credit' => 0];
                $closingLines[] = ['account_code' => $retainedEarningsAccount, 'debit' => 0, 'credit' => $netIncome];
            } else {
                // Loss: debit retained earnings, credit expense summary
                $loss = $this->mathService->multiply($netIncome, '-1');
                $closingLines[] = ['account_code' => $retainedEarningsAccount, 'debit' => $loss, 'credit' => 0];
                $closingLines[] = ['account_code' => $expenseSummaryAccount, 'debit' => 0, 'credit' => $loss];
            }
        }

        if (empty($closingLines)) {
            return [];
        }

        $entry = $this->accountingService->createJournalEntry(
            $closingLines,
            'Period_Close',
            $period->id,
            "Period close for {$period->period_code} - Net Income: RM {$netIncome}",
            $period->end_date->toDateString(),
            $closedBy
        );

        $entry->update(['period_id' => $period->id]);

        return [$entry];
    }

    /**
     * Get net ledger balances (debits minus credits) for multiple accounts.
     *
     * Aggregates all ledger rows up to the date — across every branch —
     * because running_balance chains are per-branch and the period close is
     * a company-wide operation. Picking a chain tail (e.g. MAX(id)) would
     * return one branch's balance mislabeled as consolidated, and can land
     * on a mid-chain row after backdated-posting repair.
     *
     * @param  array<int, string>  $accountCodes  Array of account codes to query
     * @param  string  $asOfDate  Date for balance calculation (YYYY-MM-DD format)
     * @return array<string, string> Account code => net (debit - credit) balance string
     */
    protected function getNetBalances(array $accountCodes, string $asOfDate): array
    {
        if (empty($accountCodes)) {
            return [];
        }

        /** @var Collection<string, object{account_code:string, td:?string, tc:?string}> $totals */
        $totals = AccountLedger::whereIn('account_code', $accountCodes)
            ->whereDate('entry_date', '<=', Carbon::parse($asOfDate)->toDateString())
            ->selectRaw('account_code, COALESCE(SUM(debit),0) as td, COALESCE(SUM(credit),0) as tc')
            ->groupBy('account_code')
            ->get()
            ->keyBy('account_code');

        $balances = [];
        foreach ($accountCodes as $code) {
            $row = $totals->get($code);
            $balances[$code] = $row
                ? $this->mathService->subtract((string) $row->td, (string) $row->tc)
                : '0';
        }

        return $balances;
    }
}
