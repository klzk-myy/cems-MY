<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Exceptions\Domain\AccountNotFoundException;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\AuditService;
use App\Services\Contracts\AccountingServiceInterface;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;

/**
 * Accounting Service
 *
 * Handles core accounting operations including journal entry creation,
 * validation, reversal, and account balance/activity queries.
 *
 * Ensures double-entry bookkeeping integrity and maintains ledger consistency.
 */
class AccountingService implements AccountingServiceInterface
{
    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Audit service for tamper-evident logging.
     */
    protected AuditService $auditService;

    /**
     * Create a new AccountingService instance.
     *
     * @param  MathService  $mathService  Math service for precise calculations
     * @param  AuditService  $auditService  Audit service for tamper-evident logging
     */
    public function __construct(
        MathService $mathService,
        AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
    ) {
        $this->mathService = $mathService;
        $this->auditService = $auditService;
    }

    /**
     * Create a new journal entry with validation.
     *
     * Validates that the entry is balanced (debits equal credits) and creates
     * in Draft status. Entries must be submitted for approval and then approved
     * before being posted to the ledger.
     *
     * @param  array  $lines  Array of journal line items with keys:
     *                        - account_code: string Account code
     *                        - debit?: float|int|string Debit amount (default: 0)
     *                        - credit?: float|int|string Credit amount (default: 0)
     *                        - description?: string Line description (optional)
     * @param  string  $referenceType  Type of reference (e.g., 'Invoice', 'Payment')
     * @param  int|null  $referenceId  Reference document ID (optional)
     * @param  string  $description  Entry description
     * @param  string|null  $entryDate  Entry date in YYYY-MM-DD format (default: today)
     * @param  int|null  $createdBy  User ID creating the entry (default: authenticated user)
     * @return JournalEntry Created journal entry with loaded lines
     *
     * @throws \InvalidArgumentException If entry is not balanced or period is closed
     */
    public function createJournalEntry(
        array $lines,
        string $referenceType,
        ?int $referenceId = null,
        string $description = '',
        ?string $entryDate = null,
        ?int $createdBy = null,
        ?int $branchId = null
    ): JournalEntry {
        $createdBy = $createdBy ?? auth()->user()?->id;
        $entryDate = $entryDate ?? now()->toDateString();

        return DB::transaction(function () use ($lines, $referenceType, $referenceId, $description, $entryDate, $createdBy, $branchId) {
            if (! $this->validateBalanced($lines)) {
                throw new AccountingPeriodException('Journal entry is not balanced: debits do not equal credits');
            }

            // Find the accounting period for this entry date
            $period = AccountingPeriod::forDate($entryDate)->first();

            // Validate that the period is open (if period exists)
            if ($period && ! $period->isOpen()) {
                throw new AccountingPeriodException(
                    "Cannot create entry in closed period {$period->period_code}. Please use an open period or contact administrator."
                );
            }

            // Create entry as Posted and post to ledger directly
            $entry = JournalEntry::create([
                'entry_date' => $entryDate,
                'period_id' => $period?->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => JournalEntryStatus::Posted->value,
                'created_by' => $createdBy,
                'posted_by' => $createdBy,
                'posted_at' => now(),
                'branch_id' => $branchId,
            ]);

            foreach ($lines as $line) {
                if (empty($line['account_code'])) {
                    throw new \InvalidArgumentException('Journal line must have a non-empty account_code');
                }

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'] ?? '0',
                    'credit' => $line['credit'] ?? '0',
                    'description' => $line['description'] ?? null,
                ]);
            }

            $this->updateLedger($entry);

            $this->auditService->log(
                'journal_entry_created',
                $createdBy,
                'JournalEntry',
                $entry->id,
                [],
                [
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => $description,
                    'status' => JournalEntryStatus::Posted->value,
                ]
            );

            return $entry->fresh()->load('lines');
        });
    }

    /**
     * Changes status from 'Pending' to 'Rejected'. The entry can be
     * edited and resubmitted, or deleted.
     *
     * @param  JournalEntry  $entry  The entry to reject
     * @param  int|null  $rejectedBy  User ID rejecting (default: authenticated user)
     * @param  string|null  $rejectionNotes  Reason for rejection
     * @return JournalEntry Updated entry
     *
     * @throws \InvalidArgumentException If entry is not in Pending status
     */
    public function rejectEntry(
        JournalEntry $entry,
        ?int $rejectedBy = null,
        ?string $rejectionNotes = null
    ): JournalEntry {
        $rejectedBy = $rejectedBy ?? auth()->user()?->id;

        return DB::transaction(function () use ($entry, $rejectionNotes) {
            // Re-fetch with a row lock to serialize concurrent reject attempts
            $entry = JournalEntry::where('id', $entry->id)->lockForUpdate()->firstOrFail();

            if (! $entry->isPending()) {
                throw new AccountingPeriodException('Only pending entries can be rejected');
            }

            $entry->update([
                'status' => JournalEntryStatus::Rejected->value,
                'approval_notes' => $rejectionNotes,
            ]);

            $this->auditService->logJournalWorkflowEvent('journal_entry_rejected', $entry->id, [
                'old' => ['status' => 'Pending'],
                'new' => [
                    'status' => JournalEntryStatus::Rejected->value,
                    'rejection_notes' => $rejectionNotes,
                ],
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Validate that journal entry lines are balanced.
     *
     * Calculates total debits and credits using high-precision arithmetic
     * and verifies they are equal.
     *
     * @param  array  $lines  Array of journal line items with keys:
     *                        - debit?: float|int|string Debit amount (default: 0)
     *                        - credit?: float|int|string Credit amount (default: 0)
     * @return bool True if debits equal credits, false otherwise
     */
    public function validateBalanced(array $lines): bool
    {
        $totalDebits = '0';
        $totalCredits = '0';

        foreach ($lines as $line) {
            $debit = (string) ($line['debit'] ?? 0);
            $credit = (string) ($line['credit'] ?? 0);
            $totalDebits = $this->mathService->add($totalDebits, $debit);
            $totalCredits = $this->mathService->add($totalCredits, $credit);
        }

        return $this->mathService->compare($totalDebits, $totalCredits) === 0;
    }

    /**
     * Reverse an existing journal entry.
     *
     * Creates a new reversal entry that swaps debits and credits from the
     * original entry. Updates original entry status to 'Reversed'.
     *
     * @param  JournalEntry  $originalEntry  The entry to reverse
     * @param  string  $reason  Reason for the reversal
     * @param  int|null  $reversedBy  User ID performing the reversal (default: authenticated user)
     * @return JournalEntry The newly created reversal entry
     *
     * @throws \InvalidArgumentException If entry is already reversed or not posted
     */
    public function reverseJournalEntry(
        JournalEntry $originalEntry,
        string $reason = '',
        ?int $reversedBy = null
    ): JournalEntry {
        $reversedBy = $reversedBy ?? auth()->user()?->id;

        return DB::transaction(function () use ($originalEntry, $reason, $reversedBy) {
            // Re-fetch with a row lock to serialize concurrent reverse attempts
            $originalEntry = JournalEntry::where('id', $originalEntry->id)->lockForUpdate()->firstOrFail();

            // Validation 1: Check if entry is already reversed
            if ($originalEntry->isReversed()) {
                throw new AccountingPeriodException('Entry has already been reversed');
            }

            // Validation 2: Check if entry is posted (can only reverse posted entries)
            if (! $originalEntry->isPosted()) {
                throw new AccountingPeriodException('Entry must be Posted to be reversed');
            }

            // Load lines if not already loaded
            if (! $originalEntry->relationLoaded('lines')) {
                $originalEntry->load('lines');
            }

            // Create reversal entry FIRST (so we can link to it)
            $lines = [];
            foreach ($originalEntry->lines as $line) {
                $lines[] = [
                    'account_code' => $line->account_code,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => 'Reversal: '.$line->description,
                ];
            }

            $entry = $this->createJournalEntry(
                $lines,
                'Reversal',
                $originalEntry->id,
                "Reversal of entry {$originalEntry->id}: {$reason}",
                now()->toDateString(),
                $reversedBy,
                $originalEntry->branch_id
            );

            // Reversal entry is posted directly by createJournalEntry

            // Update original entry status and create explicit link via reversal_id
            $originalEntry->update([
                'status' => JournalEntryStatus::Reversed->value,
                'reversed_by' => $reversedBy,
                'reversed_at' => now(),
            ]);

            return $entry;
        });
    }

    /**
     * Update the account ledger with journal entry lines.
     *
     * @param  JournalEntry  $entry  The journal entry to process
     */
    protected function updateLedger(JournalEntry $entry): void
    {
        foreach ($entry->lines as $line) {
            // Serialize writers per account: lock the chart-of-accounts anchor
            // row so a concurrent posting to the same account cannot read the
            // same running_balance and corrupt the balance chain. The lock is
            // held until the enclosing transaction commits (no-op on SQLite).
            // isDebitAccount() already resolves the account; this lock must
            // happen BEFORE the balance read below.
            ChartOfAccount::where('account_code', $line->account_code)
                ->lockForUpdate()
                ->firstOrFail();

            // Scope the running balance to the entry's branch so multi-branch
            // ledger activity can never contaminate another branch's balance.
            $currentBalance = $this->getAccountBalance($line->account_code, null, $entry->branch_id);

            if ($this->isDebitAccount($line->account_code)) {
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
                'branch_id' => $entry->branch_id,
                'entry_date' => $entry->entry_date,
                'journal_entry_id' => $entry->id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => $newBalance,
            ]);
        }

        // Ledger financial reports are cached under the 'ledger' tag; flush it
        // so trial balances/balance sheets are not stale after a posting.
        $this->cacheInvalidationService->invalidate('ledger');

        // Cash-flow and report aggregates derive from the same ledger rows and
        // are cached under the 'reports' tag; flush it too or cash-flow views
        // stay stale up to their TTL after every posting.
        $this->cacheInvalidationService->invalidate('reports');
    }

    /**
     * Determine if an account is a debit-balance account.
     *
     * @param  string  $accountCode  The account code to check
     * @return bool True if account type is Asset or Expense
     *
     * @throws \InvalidArgumentException If account is not found
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
     * Get the current balance for an account.
     *
     * Retrieves the running balance from the most recent ledger entry,
     * optionally filtered by an as-of date and branch. This is the single
     * canonical implementation; LedgerService, FiscalYearService and
     * FinancialRatioService all delegate here (previously each duplicated
     * this query).
     *
     * @param  string  $accountCode  The account code to query
     * @param  string|null  $asOfDate  Date in YYYY-MM-DD format (default: current date)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return string Account balance as a string for precision
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null, ?int $branchId = null): string
    {
        $query = AccountLedger::where('account_code', $accountCode);

        if ($asOfDate) {
            // Use date function for cross-database compatibility
            // This ensures proper comparison regardless of datetime vs date storage
            $query->whereRaw('DATE(entry_date) <= ?', [$asOfDate]);
        }

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $lastEntry = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (string) $lastEntry->running_balance : '0';
    }

    /**
     * Get net account activity (change in balance) within a date range.
     *
     * Calculates the net movement of an account between two dates.
     * For expense accounts, this returns total debits minus credits.
     *
     * @param  string  $accountCode  The account code to query
     * @param  string  $startDate  Start date in YYYY-MM-DD format (inclusive)
     * @param  string  $endDate  End date in YYYY-MM-DD format (inclusive)
     * @return string Net activity amount as a string (positive = net debit, negative = net credit)
     */
    public function getAccountActivity(string $accountCode, string $startDate, string $endDate): string
    {
        $totals = AccountLedger::where('account_code', $accountCode)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        // Net activity: debits - credits (expense-normal).
        return $this->mathService->subtract(
            (string) ($totals->total_debit ?? 0),
            (string) ($totals->total_credit ?? 0)
        );
    }

    /**
     * Get account activity for many account codes in a single query.
     *
     * @param  array<int, string>  $accountCodes
     * @return array<string, string>
     */
    public function getAccountsActivity(array $accountCodes, string $fromDate, string $toDate): array
    {
        if (empty($accountCodes)) {
            return [];
        }

        $rows = AccountLedger::query()
            ->select('account_code')
            ->selectRaw('SUM(debit - credit) as activity')
            ->whereIn('account_code', $accountCodes)
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->groupBy('account_code')
            ->pluck('activity', 'account_code')
            ->toArray();

        return array_map('strval', $rows);
    }
}
