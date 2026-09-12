<?php

namespace App\Services\Accounting;

use App\Enums\AccountCode;
use App\Enums\AccountingPeriodType;
use App\Enums\AccountType;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Exceptions\Domain\AccountNotFoundException;
use App\Exceptions\Domain\FiscalYearClosedException;
use App\Exceptions\Domain\FiscalYearNotFoundException;
use App\Exceptions\Domain\InvalidFiscalYearStateException;
use App\Exceptions\Domain\OpenPeriodsException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
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
        protected AuditService $auditService,
        protected MathService $mathService,
        protected LedgerService $ledgerService,
        protected CacheInvalidationService $cacheInvalidationService,
        protected AccountingService $accountingService,
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
        $year = FiscalYear::create([
            'year_code' => $yearCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'Open',
        ]);

        // Attach any existing unlinked periods that fall inside the year so
        // the year view and closeFiscalYear()'s all-periods-closed guard see them.
        AccountingPeriod::whereNull('fiscal_year_id')
            ->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->update(['fiscal_year_id' => $year->id]);

        return $year;
    }

    /**
     * Close a fiscal year.
     *
     * Creates closing entries:
     * 1. Close all Revenue accounts → Income Summary (4201)
     * 2. Close all Expense accounts → Income Summary (4201)
     * 3. Close Income Summary → Retained Earnings (4100)
     *
     * @param  int|null  $userId  Optional user ID for testing (defaults to auth()->id())
     * @return array Year-end report data
     *
     * @throws \InvalidArgumentException
     */
    public function closeFiscalYear(FiscalYear $year, ?int $userId = null): array
    {
        $userId = $userId ?? auth()->user()?->id;
        // Validate user permissions
        if ($userId === null || ! $this->canCloseYear(User::find($userId))) {
            throw new PermissionDeniedException('close fiscal years');
        }

        // Check if year is already closed
        if ($year->isClosed()) {
            throw new FiscalYearClosedException;
        }

        return DB::transaction(function () use ($year, $userId) {
            // Lock the fiscal-year row and re-validate under the lock so two
            // concurrent closes serialise instead of double-booking the
            // deterministic CE-Ym-* closing entries.
            $lockedYear = FiscalYear::where('id', $year->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedYear || $lockedYear->isClosed()) {
                throw new FiscalYearClosedException;
            }

            // Validate all periods in the year are closed
            $this->validateAllPeriodsClosed($lockedYear);

            $yearEndDate = $lockedYear->end_date->toDateString();

            // Step 1: Get revenue and expense totals
            $revenueTotal = $this->getAccountTypeTotal('Revenue', $lockedYear->start_date->toDateString(), $yearEndDate);
            $expenseTotal = $this->getAccountTypeTotal('Expense', $lockedYear->start_date->toDateString(), $yearEndDate);
            $netIncome = $this->mathService->subtract($revenueTotal, $expenseTotal);

            // Step 2: Create closing entries
            $closingEntries = [];

            // Close Revenue accounts to Income Summary (4201)
            if ($this->mathService->compare($revenueTotal, '0') !== 0) {
                $closingEntries[] = $this->closeRevenueToIncomeSummary($revenueTotal, $yearEndDate, $userId);
            }

            // Close Expense accounts to Income Summary (4201)
            if ($this->mathService->compare($expenseTotal, '0') !== 0) {
                $closingEntries[] = $this->closeExpensesToIncomeSummary($expenseTotal, $yearEndDate, $userId);
            }

            // Close Income Summary to Retained Earnings (4100)
            if ($this->mathService->compare($netIncome, '0') !== 0) {
                $closingEntries[] = $this->closeIncomeSummaryToRetained($netIncome, $yearEndDate, $userId);
            }

            // Update fiscal year status
            $lockedYear->update([
                'status' => 'Closed',
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            $this->auditService->log(
                'fiscal_year_closed',
                $userId,
                'FiscalYear',
                $lockedYear->id,
                [],
                [
                    'year_code' => $lockedYear->year_code,
                    'net_income' => $netIncome,
                ],
            );

            return [
                'fiscal_year' => $lockedYear->fresh(),
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
        $year = FiscalYear::where('year_code', $yearCode)->first();

        if (! $year) {
            throw new FiscalYearNotFoundException($yearCode);
        }

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

        // No opening journal entry is created: the account ledger is
        // continuous across fiscal-year boundaries, so every account's closing
        // balance (including retained earnings, already rolled up by the
        // closing entries) carries forward automatically. The previous
        // implementation posted a single-sided Retained Earnings line dated at
        // the CLOSED year's start — an unbalanced entry that double-counted
        // equity in the books.
        return DB::transaction(function () use ($year, $userId) {
            $userId = $userId ?? auth()->user()?->id;

            $this->auditService->log(
                'fiscal_year_opened',
                $userId,
                'FiscalYear',
                $year->id,
                [],
                ['year_code' => $year->year_code]
            );

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

        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Validate all periods in the fiscal year are closed.
     */
    protected function validateAllPeriodsClosed(FiscalYear $year): void
    {
        $openPeriods = $year->periods()->where('status', 'Open')->count();

        if ($openPeriods > 0) {
            throw new OpenPeriodsException($openPeriods);
        }
    }

    /**
     * Get aggregated closing balances for a set of account codes.
     *
     * Returns AccountLedger models augmented with the aggregate columns
     * `total_debit` and `total_credit`, keyed by account_code.
     *
     * @return Collection<int, AccountLedger>
     */
    protected function getClosingBalancesForAccounts(array $accountCodes, string $entryDate): Collection
    {
        return AccountLedger::whereRaw('DATE(entry_date) <= ?', [$entryDate])
            ->whereIn('account_code', $accountCodes)
            ->select('account_code', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->groupBy('account_code')
            ->get()
            ->keyBy('account_code');
    }

    /**
     * Close revenue accounts to income summary.
     */
    protected function closeRevenueToIncomeSummary(string $total, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = $this->generateEntryNumber($entryDate);

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
        $balances = $this->getClosingBalancesForAccounts($revenueAccounts->pluck('account_code')->toArray(), $entryDate);

        foreach ($revenueAccounts as $account) {
            $row = $balances->get($account->account_code);
            $balance = $row
                ? $this->mathService->subtract((string) $row->total_credit, (string) $row->total_debit)
                : '0';

            if ($this->mathService->compare($balance, '0') > 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => 'Close '.$account->account_name,
                ]);
            } elseif ($this->mathService->compare($balance, '0') < 0) {
                // Contra-revenue (net debit balance): close it with a credit
                // rather than posting a negative debit line.
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => 0,
                    'credit' => $this->mathService->multiply($balance, '-1'),
                    'description' => 'Close '.$account->account_name,
                ]);
            }
        }

        // Credit Income Summary (debit instead when total revenue is negative)
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => AccountCode::INCOME_SUMMARY->value,
            'debit' => $this->mathService->compare($total, '0') < 0 ? $this->mathService->multiply($total, '-1') : 0,
            'credit' => $this->mathService->compare($total, '0') >= 0 ? $total : 0,
            'description' => 'Income Summary',
        ]);

        // Create ledger entries
        $this->postClosingToLedger($entry);

        return $entry;
    }

    /**
     * Close expense accounts to income summary.
     */
    protected function closeExpensesToIncomeSummary(string $total, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = $this->generateEntryNumber($entryDate, '002');

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'period_id' => $this->getPeriodId($entryDate),
            'reference_type' => 'FiscalYearClosing',
            'description' => 'Closing Expenses to Income Summary',
            'status' => 'Posted',
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);

        // Credit each expense account
        $expenseAccounts = ChartOfAccount::where('account_type', 'Expense')->get();
        $balances = $this->getClosingBalancesForAccounts($expenseAccounts->pluck('account_code')->toArray(), $entryDate);

        foreach ($expenseAccounts as $account) {
            $row = $balances->get($account->account_code);
            $balance = $row
                ? $this->mathService->subtract((string) $row->total_debit, (string) $row->total_credit)
                : '0';

            if ($this->mathService->compare($balance, '0') > 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => 'Close '.$account->account_name,
                ]);
            } elseif ($this->mathService->compare($balance, '0') < 0) {
                // Contra-expense (net credit balance): close it with a debit
                // rather than posting a negative credit line.
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $account->account_code,
                    'debit' => $this->mathService->multiply($balance, '-1'),
                    'credit' => 0,
                    'description' => 'Close '.$account->account_name,
                ]);
            }
        }

        // Debit Income Summary (credit instead when total expense is negative)
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => AccountCode::INCOME_SUMMARY->value,
            'debit' => $this->mathService->compare($total, '0') >= 0 ? $total : 0,
            'credit' => $this->mathService->compare($total, '0') < 0 ? $this->mathService->multiply($total, '-1') : 0,
            'description' => 'Income Summary',
        ]);

        // Create ledger entries
        $this->postClosingToLedger($entry);

        return $entry;
    }

    /**
     * Close income summary to retained earnings.
     */
    protected function closeIncomeSummaryToRetained(string $netIncome, string $entryDate, int $userId): JournalEntry
    {
        $entryNumber = $this->generateEntryNumber($entryDate, '003');

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'period_id' => $this->getPeriodId($entryDate),
            'reference_type' => 'FiscalYearClosing',
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
                'account_code' => AccountCode::INCOME_SUMMARY->value,
                'debit' => $netIncome,
                'credit' => 0,
                'description' => 'Close Income Summary',
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => AccountCode::RETAINED_EARNINGS->value,
                'debit' => 0,
                'credit' => $netIncome,
                'description' => 'Transfer to Retained Earnings',
            ]);
        } else {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => AccountCode::INCOME_SUMMARY->value,
                'debit' => 0,
                'credit' => $this->mathService->abs($netIncome),
                'description' => 'Close Income Summary (Loss)',
            ]);
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_code' => AccountCode::RETAINED_EARNINGS->value,
                'debit' => $this->mathService->abs($netIncome),
                'credit' => 0,
                'description' => 'Transfer to Retained Earnings (Loss)',
            ]);
        }

        // Create ledger entries
        $this->postClosingToLedger($entry);

        return $entry;
    }

    /**
     * Post a closing journal entry's lines to the account ledger.
     *
     * Delegates to AccountingService::postToLedger so ledger writes use the
     * single canonical implementation (account-direction rules, chart-row
     * locking, backdate chain repair, ledger+reports cache invalidation).
     * The previous local copy special-cased Income Summary (4201) as
     * debit-normal, which gave the same account two different running-balance
     * conventions depending on which service wrote the row.
     */
    protected function postClosingToLedger(JournalEntry $entry): void
    {
        $this->accountingService->postToLedger($entry);
    }

    /**
     * Get account type total for a period.
     */
    protected function getAccountTypeTotal(string $accountType, string $fromDate, string $toDate): string
    {
        $total = '0';
        $accounts = ChartOfAccount::where('account_type', $accountType)->get();
        $accountCodes = $accounts->pluck('account_code')->toArray();

        if (empty($accountCodes)) {
            return $total;
        }

        /** @var Collection<int, object{account_code:string, total_debit:?string, total_credit:?string}> $totals */
        $totals = AccountLedger::whereIn('account_code', $accountCodes)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->selectRaw('account_code, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('account_code')
            ->get()
            ->keyBy('account_code');

        foreach ($accounts as $account) {
            $ledgerTotals = $totals->get($account->account_code);
            if ($accountType === 'Revenue') {
                $credits = $ledgerTotals ? (string) $ledgerTotals->total_credit : '0';
                $debits = $ledgerTotals ? (string) $ledgerTotals->total_debit : '0';
                $balance = $this->mathService->subtract($credits, $debits);
            } else {
                $debits = $ledgerTotals ? (string) $ledgerTotals->total_debit : '0';
                $credits = $ledgerTotals ? (string) $ledgerTotals->total_credit : '0';
                $balance = $this->mathService->subtract($debits, $credits);
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
     *
     * Delegates to the canonical running-balance lookup in LedgerService
     * (which itself delegates to AccountingService). The delegate includes
     * the id tie-breaker this method previously lacked, so entries posted
     * within the same second resolve deterministically.
     */
    protected function getAccountBalance(string $accountCode, string $asOfDate): string
    {
        return $this->ledgerService->getAccountBalance($accountCode, $asOfDate);
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

        return $account->account_type instanceof AccountType
            ? $account->account_type->isDebitNormal()
            : in_array($account->account_type, ['Asset', 'Expense']);
    }

    /**
     * Generate a closing entry number for a date.
     */
    public function generateEntryNumber(string $entryDate, string $suffix = '001'): string
    {
        $timestamp = strtotime($entryDate);
        if ($timestamp === false) {
            throw new AccountingPeriodException("Invalid entry date: {$entryDate}");
        }

        return 'CE-'.date('Ym', $timestamp).'-'.$suffix;
    }

    /**
     * Get period ID for a date.
     */
    protected function getPeriodId(string $date): ?int
    {
        $period = AccountingPeriod::forDate($date)->first();

        return $period?->id;
    }

    public function createPeriod(
        string $periodCode,
        string $startDate,
        string $endDate,
        AccountingPeriodType $type,
        ?int $fiscalYearId = null,
    ): AccountingPeriod {
        return AccountingPeriod::create([
            'period_code' => $periodCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_type' => $type,
            'status' => 'Open',
            'fiscal_year_id' => $fiscalYearId,
        ]);
    }

    public function createQuarterPeriods(FiscalYear $year): array
    {
        return DB::transaction(function () use ($year) {
            // Each quarter ends the day before the next begins; without the
            // -1 day the boundary date belonged to both quarters and
            // AccountingPeriod::forDate() resolved it ambiguously.
            $q2Start = (new \DateTime($year->start_date))->modify('+3 months');
            $q3Start = (new \DateTime($year->start_date))->modify('+6 months');
            $q4Start = (new \DateTime($year->start_date))->modify('+9 months');

            $quarters = [
                ['Q1', $year->start_date, (clone $q2Start)->modify('-1 day')->format('Y-m-d')],
                ['Q2', $q2Start->format('Y-m-d'), (clone $q3Start)->modify('-1 day')->format('Y-m-d')],
                ['Q3', $q3Start->format('Y-m-d'), (clone $q4Start)->modify('-1 day')->format('Y-m-d')],
                ['Q4', $q4Start->format('Y-m-d'), $year->end_date],
            ];

            $periods = [];
            foreach ($quarters as [$code, $start, $end]) {
                $periods[] = $this->createPeriod(
                    $year->year_code.'-'.$code,
                    $start,
                    $end,
                    AccountingPeriodType::Quarter,
                    $year->id,
                );
            }

            return $periods;
        });
    }

    public function createYearPeriod(FiscalYear $year): AccountingPeriod
    {
        return $this->createPeriod(
            $year->year_code,
            $year->start_date,
            $year->end_date,
            AccountingPeriodType::Year,
            $year->id,
        );
    }
}
