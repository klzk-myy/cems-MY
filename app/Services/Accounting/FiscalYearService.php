<?php

namespace App\Services\Accounting;

use App\Enums\AccountCode;
use App\Enums\AccountingPeriodType;
use App\Enums\Permission;
use App\Exceptions\Domain\AccountingPeriodException;
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
use App\Support\ActorContext;
use Carbon\Carbon;
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

        // A fiscal year without periods accepts no postings — generate one
        // open monthly period per month covered by the year.
        $month = Carbon::parse($startDate)->startOfMonth();
        $last = Carbon::parse($endDate)->endOfMonth();

        while ($month->lte($last)) {
            AccountingPeriod::firstOrCreate(
                ['period_code' => $month->format('Y-m')],
                [
                    'fiscal_year_id' => $year->id,
                    'start_date' => $month->copy()->startOfMonth()->toDateString(),
                    'end_date' => $month->copy()->endOfMonth()->toDateString(),
                    'period_type' => AccountingPeriodType::Month->value,
                    'status' => 'Open',
                ]
            );
            $month->addMonth();
        }

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
        $userId = $userId ?? ActorContext::capture()->userId;
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

            // Close Income Summary to Retained Earnings (4100). Transfer the
            // account's actual balance — not the year-range netIncome — so a
            // residue from an unclosed prior year is swept out too. A credit
            // balance (profit) reads positive for the credit-normal summary
            // account; closeIncomeSummaryToRetained handles either sign.
            $incomeSummaryBalance = $this->getAccountBalance(
                AccountCode::INCOME_SUMMARY->value,
                $yearEndDate
            );
            if ($this->mathService->compare($incomeSummaryBalance, '0') !== 0) {
                $closingEntries[] = $this->closeIncomeSummaryToRetained($incomeSummaryBalance, $yearEndDate, $userId);
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
     * Check if user can close fiscal years.
     */
    protected function canCloseYear(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->role->canPerform(Permission::ManageAccounting);
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

        // The summary offset must equal the sum of the lines actually posted.
        // Account balances are cumulative (<= year end), which can differ from
        // the year-range $total when a prior year was never closed.
        $postedTotal = '0';

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
            } else {
                continue;
            }

            $postedTotal = $this->mathService->add($postedTotal, $balance);
        }

        // Credit Income Summary (debit instead when total revenue is negative)
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => AccountCode::INCOME_SUMMARY->value,
            'debit' => $this->mathService->compare($postedTotal, '0') < 0 ? $this->mathService->multiply($postedTotal, '-1') : 0,
            'credit' => $this->mathService->compare($postedTotal, '0') >= 0 ? $postedTotal : 0,
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

        // Same rule as the revenue close: offset the sum of the posted lines,
        // not the range-scoped $total passed in.
        $postedTotal = '0';

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
            } else {
                continue;
            }

            $postedTotal = $this->mathService->add($postedTotal, $balance);
        }

        // Debit Income Summary (credit instead when total expense is negative)
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_code' => AccountCode::INCOME_SUMMARY->value,
            'debit' => $this->mathService->compare($postedTotal, '0') >= 0 ? $postedTotal : 0,
            'credit' => $this->mathService->compare($postedTotal, '0') < 0 ? $this->mathService->multiply($postedTotal, '-1') : 0,
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
        // Closing entries are built directly (not via createJournalEntry), so
        // validate the balance invariant here before touching the ledger.
        $entry->loadMissing('lines');
        $totalDebits = '0';
        $totalCredits = '0';
        foreach ($entry->lines as $line) {
            $totalDebits = $this->mathService->add($totalDebits, (string) $line->debit);
            $totalCredits = $this->mathService->add($totalCredits, (string) $line->credit);
        }

        if ($this->mathService->compare($totalDebits, $totalCredits) !== 0) {
            throw new InvalidFiscalYearStateException(
                "Closing entry {$entry->entry_number} is unbalanced: debits {$totalDebits} != credits {$totalCredits}"
            );
        }

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
}
