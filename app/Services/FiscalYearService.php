<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\Domain\AccountNotFoundException;
use App\Exceptions\Domain\FiscalYearClosedException;
use App\Exceptions\Domain\InvalidFiscalYearStateException;
use App\Exceptions\Domain\OpenPeriodsException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal Year Service
 *
 * Handles fiscal year management including creation, year-end closing,
 * and opening balance transfer for new fiscal years.
 */
class FiscalYearService
{
    /**
     * Create a new FiscalYearService instance.
     */
    public function __construct(
        protected MathService $mathService,
        protected LedgerService $ledgerService,
    ) {}

    /**
     * Create a new fiscal year.
     *
     * @param  string  $yearCode  Fiscal year code (e.g., 'FY2026')
     * @param  string  $startDate  Start date (YYYY-MM-DD)
     * @param  string  $endDate  End date (YYYY-MM-DD)
     */
    public function createFiscalYear(string $yearCode, string $startDate, string $endDate): FiscalYear
    {
        return FiscalYear::create([
            'year_code' => $yearCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'Open',
        ]);
    }

    /**
     * Close a fiscal year.
     *
     * Creates closing entries:
     * 1. Close all Revenue accounts → Income Summary (4998)
     * 2. Close all Expense accounts → Income Summary (4998)
     * 3. Close Income Summary → Retained Earnings (4999)
     *
     * @param  int|null  $userId  Optional user ID for testing (defaults to auth()->id())
     * @return array Year-end report data
     *
     * @throws \InvalidArgumentException
     */
    public function closeFiscalYear(FiscalYear $year, ?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();
        $user = User::find($userId);

        // Validate user permissions
        if (! $this->canCloseYear($user)) {
            throw new PermissionDeniedException('close fiscal years');
        }

        // Check if year is already closed
        if ($year->isClosed()) {
            throw new FiscalYearClosedException;
        }

        // Validate all periods in the year are closed
        $this->validateAllPeriodsClosed($year);

        return DB::transaction(function () use ($year, $userId) {
            $yearEndDate = $year->end_date->toDateString();

            // Step 1: Get revenue and expense totals
            $revenueTotal = $this->getAccountTypeTotal('Revenue', $year->start_date->toDateString(), $yearEndDate);
            $expenseTotal = $this->getAccountTypeTotal('Expense', $year->start_date->toDateString(), $yearEndDate);
            $netIncome = $this->mathService->subtract($revenueTotal, $expenseTotal);

            // Step 2: Create closing entries
            $closingEntries = [];

            // Close Revenue accounts to Income Summary (4998)
            if ($this->mathService->compare($revenueTotal, '0') !== 0) {
                $closingEntries[] = $this->closeRevenueToIncomeSummary($revenueTotal, $yearEndDate, $userId);
            }

            // Close Expense accounts to Income Summary (4998)
            if ($this->mathService->compare($expenseTotal, '0') !== 0) {
                $closingEntries[] = $this->closeExpensesToIncomeSummary($expenseTotal, $yearEndDate, $userId);
            }

            // Close Income Summary to Retained Earnings (4999)
            if ($this->mathService->compare($netIncome, '0') !== 0) {
                $closingEntries[] = $this->closeIncomeSummaryToRetained($netIncome, $yearEndDate, $userId);
            }

            // Update fiscal year status
            $year->update([
                'status' => 'Closed',
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'fiscal_year_closed',
                'entity_type' => 'FiscalYear',
                'entity_id' => $year->id,
                'new_values' => [
                    'year_code' => $year->year_code,
                    'net_income' => $netIncome,
                ],
                'ip_address' => request()->ip(),
            ]);

            return [
                'fiscal_year' => $year->fresh(),
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'net_income' => $netIncome,
                'closing_entries' => $closingEntries,
            ];
        });
    }

    /**
     * Get year-end report for a fiscal year.
     */
    public function getYearEndReport(string $yearCode): array
    {
        $year = FiscalYear::where('year_code', $yearCode)->firstOrFail();
        $yearEndDate = $year->end_date->toDateString();

        // Get trial balance as of year-end
        $trialBalance = $this->ledgerService->getTrialBalance($yearEndDate);

        // Get P&L summary
        $pAndL = $this->ledgerService->getProfitAndLoss(
            $year->start_date->toDateString(),
            $yearEndDate
        );

        return [
            'fiscal_year' => $year,
            'as_of_date' => $yearEndDate,
            'trial_balance' => $trialBalance,
            'profit_and_loss' => $pAndL,
            'net_income' => $pAndL['net_income'] ?? '0',
        ];
    }

    /**
     * Open a new fiscal year with opening balances.
     *
     * @param  int|null  $userId  Optional user ID for testing (defaults to auth()->id())
     */
    public function openNewFiscalYear(FiscalYear $year, ?int $userId = null): FiscalYear
    {
        if (! $year->isClosed()) {
            throw new InvalidFiscalYearStateException('Only closed fiscal years can be opened');
        }

        // Create opening entries to transfer retained earnings
        return DB::transaction(function () use ($year, $userId) {
            $userId = $userId ?? auth()->id();
            $openingDate = $year->start_date->toDateString();

            // Get retained earnings from closing
            $retainedEarnings = $this->getAccountBalance('4999', $year->end_date->toDateString());

            // Create opening entry
            $entryNumber = 'OE-'.$year->year_code.'-0001';

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $openingDate,
                'period_id' => $this->getPeriodId($openingDate),
                'reference_type' => 'FiscalYearOpening',
                'description' => 'Opening balances for '.$year->year_code,
                'status' => 'Posted',
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // Retained earnings should equal the current year P&L from prior year
            if ($this->mathService->compare($retainedEarnings, '0') !== 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => '4999',
                    'debit' => $this->mathService->compare($retainedEarnings, '0') < 0 ? abs($retainedEarnings) : 0,
                    'credit' => $this->mathService->compare($retainedEarnings, '0') >= 0 ? $retainedEarnings : 0,
                    'description' => 'Opening retained earnings',
                ]);
            }

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'fiscal_year_opened',
                'entity_type' => 'FiscalYear',
                'entity_id' => $year->id,
                'new_values' => ['year_code' => $year->year_code],
                'ip_address' => request()->ip(),
            ]);

            return $year;
        });
    }

    /**
     * Check if user can close fiscal years.
     */
    protected function canCloseYear(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::Admin || $role === UserRole::Manager;
        }

        return in_array($role, ['admin', 'manager']);
    }

    /**
     * Validate all periods in the fiscal year are closed.
     */
    protected function validateAllPeriodsClosed(FiscalYear $year): void
    {
        $openPeriods = $year->periods()->where('status', 'open')->count();

        if ($openPeriods > 0) {
            throw new OpenPeriodsException($openPeriods);
        }
    }

    /**
     * Close revenue accounts to income summary.
     */
    protected function closeRevenueToIncomeSummary(string $total, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = 'CE-'.date('Ym', strtotime($entryDate)).'-001';

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'period_id' => $this->getPeriodId($entryDate),
            'reference_type' => 'FiscalYearClosing',
            'description' => 'Closing Revenue to Income Summary',
            'status' => 'Posted',
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);

        // Debit each revenue account
        $revenueAccounts = ChartOfAccount::where('account_type', 'Revenue')->get();
        foreach ($revenueAccounts as $account) {
            $balance = $this->getAccountBalanceForClosing($account->account_code, $entryDate, 'credit');
            if ($this->mathService->compare($balance, '0') !== 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => 'Close '.$account->account_name,
                ]);
            }
        }

        // Credit Income Summary
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => '4998',
            'debit' => 0,
            'credit' => $total,
            'description' => 'Income Summary',
        ]);

        // Create ledger entries
        $this->createClosingLedgerEntries($entry);

        return $entry;
    }

    /**
     * Close expense accounts to income summary.
     */
    protected function closeExpensesToIncomeSummary(string $total, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = 'CE-'.date('Ym', strtotime($entryDate)).'-002';

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'period_id' => $this->getPeriodId($entryDate),
            'description' => 'Closing Expenses to Income Summary',
            'status' => 'Posted',
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);

        // Credit each expense account
        $expenseAccounts = ChartOfAccount::where('account_type', 'Expense')->get();
        foreach ($expenseAccounts as $account) {
            $balance = $this->getAccountBalanceForClosing($account->account_code, $entryDate, 'debit');
            if ($this->mathService->compare($balance, '0') !== 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => 'Close '.$account->account_name,
                ]);
            }
        }

        // Debit Income Summary
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => '4998',
            'debit' => $total,
            'credit' => 0,
            'description' => 'Income Summary',
        ]);

        // Create ledger entries
        $this->createClosingLedgerEntries($entry);

        return $entry;
    }

    /**
     * Close income summary to retained earnings.
     */
    protected function closeIncomeSummaryToRetained(string $netIncome, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = 'CE-'.date('Ym', strtotime($entryDate)).'-003';

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'period_id' => $this->getPeriodId($entryDate),
            'description' => 'Close Income Summary to Retained Earnings',
            'status' => 'Posted',
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);

        // Net income positive = credit retained earnings (profit)
        // Net income negative = debit retained earnings (loss)
        if ($this->mathService->compare($netIncome, '0') >= 0) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => '4998',
                'debit' => $netIncome,
                'credit' => 0,
                'description' => 'Close Income Summary',
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => '4999',
                'debit' => 0,
                'credit' => $netIncome,
                'description' => 'Transfer to Retained Earnings',
            ]);
        } else {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => '4998',
                'debit' => 0,
                'credit' => abs($netIncome),
                'description' => 'Close Income Summary (Loss)',
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => '4999',
                'debit' => abs($netIncome),
                'credit' => 0,
                'description' => 'Transfer to Retained Earnings (Loss)',
            ]);
        }

        // Create ledger entries
        $this->createClosingLedgerEntries($entry);

        return $entry;
    }

    /**
     * Create ledger entries for closing entries.
     */
    protected function createClosingLedgerEntries(JournalEntry $entry): void
    {
        foreach ($entry->lines as $line) {
            $currentBalance = $this->getAccountBalance($line->account_code, $entry->entry_date);

            // Income Summary (4998) is treated as a special debit-normal equity account
            // for closing entry calculations, despite being classified as Equity.
            // When closing revenue: credit to 4998 increases balance
            // When closing expenses: debit to 4998 decreases balance
            if ($line->account_code === '4998') {
                $newBalance = $this->mathService->add(
                    $this->mathService->add($currentBalance, (string) $line->debit),
                    $this->mathService->multiply((string) $line->credit, '-1')
                );
            } elseif ($this->isDebitAccount($line->account_code)) {
                $newBalance = $this->mathService->add(
                    $this->mathService->add($currentBalance, (string) $line->debit),
                    $this->mathService->multiply((string) $line->credit, '-1')
                );
            } else {
                $newBalance = $this->mathService->add(
                    $this->mathService->add($currentBalance, (string) $line->credit),
                    $this->mathService->multiply((string) $line->debit, '-1')
                );
            }

            AccountLedger::create([
                'account_code' => $line->account_code,
                'entry_date' => $entry->entry_date,
                'journal_entry_id' => $entry->id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => $newBalance,
            ]);
        }
    }

    /**
     * Get account type total for a period.
     */
    protected function getAccountTypeTotal(string $accountType, string $fromDate, string $toDate): string
    {
        $total = '0';
        $accounts = ChartOfAccount::where('account_type', $accountType)->get();

        foreach ($accounts as $account) {
            if ($accountType === 'Revenue') {
                $credits = AccountLedger::where('account_code', $account->account_code)
                    ->whereBetween('entry_date', [$fromDate, $toDate])
                    ->sum('credit');
                $debits = AccountLedger::where('account_code', $account->account_code)
                    ->whereBetween('entry_date', [$fromDate, $toDate])
                    ->sum('debit');
                $balance = $this->mathService->subtract((string) $credits, (string) $debits);
            } else {
                $debits = AccountLedger::where('account_code', $account->account_code)
                    ->whereBetween('entry_date', [$fromDate, $toDate])
                    ->sum('debit');
                $credits = AccountLedger::where('account_code', $account->account_code)
                    ->whereBetween('entry_date', [$fromDate, $toDate])
                    ->sum('credit');
                $balance = $this->mathService->subtract((string) $debits, (string) $credits);
            }
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get account balance for closing purposes.
     */
    protected function getAccountBalanceForClosing(string $accountCode, string $asOfDate, string $type): string
    {
        if ($type === 'credit') {
            $credits = AccountLedger::where('account_code', $accountCode)
                ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
                ->sum('credit');
            $debits = AccountLedger::where('account_code', $accountCode)
                ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
                ->sum('debit');

            return $this->mathService->subtract((string) $credits, (string) $debits);
        } else {
            $debits = AccountLedger::where('account_code', $accountCode)
                ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
                ->sum('debit');
            $credits = AccountLedger::where('account_code', $accountCode)
                ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
                ->sum('credit');

            return $this->mathService->subtract((string) $debits, (string) $credits);
        }
    }

    /**
     * Get account balance as of a date.
     */
    protected function getAccountBalance(string $accountCode, string $asOfDate): string
    {
        $lastEntry = AccountLedger::where('account_code', $accountCode)
            ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastEntry ? (string) $lastEntry->running_balance : '0';
    }

    /**
     * Check if account is a debit-balance account.
     */
    protected function isDebitAccount(string $accountCode): bool
    {
        $account = ChartOfAccount::find($accountCode);
        if (! $account) {
            throw new AccountNotFoundException($accountCode);
        }

        return in_array($account->account_type, ['Asset', 'Expense']);
    }

    /**
     * Get period ID for a date.
     */
    protected function getPeriodId(string $date): ?int
    {
        $period = AccountingPeriod::forDate($date)->first();

        return $period?->id;
    }
}
