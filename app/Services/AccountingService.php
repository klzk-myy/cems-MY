<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AccountNotFoundException;
use App\Exceptions\Domain\ClosedPeriodException;
use App\Exceptions\Domain\EntryAlreadyReversedException;
use App\Exceptions\Domain\EntryNotPostedException;
use App\Exceptions\Domain\UnbalancedJournalException;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\CurrencyPosition;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TillBalance;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Accounting Service
 *
 * Handles core accounting operations including journal entry creation,
 * validation, reversal, and account balance/activity queries.
 *
 * Ensures double-entry bookkeeping integrity and maintains ledger consistency.
 */
class AccountingService
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
    public function __construct(MathService $mathService, AuditService $auditService)
    {
        $this->mathService = $mathService;
        $this->auditService = $auditService;
    }

    /**
     * Create a new journal entry with validation and post directly to ledger.
     *
     * Validates that the entry is balanced (debits equal credits) and creates
     * in Posted status, immediately posting to the general ledger.
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
        ?int $createdBy = null
    ): JournalEntry {
        $createdBy = $createdBy ?? auth()->id();
        $entryDate = $entryDate ?? now()->toDateString();

        return DB::transaction(function () use ($lines, $referenceType, $referenceId, $description, $entryDate, $createdBy) {
            if (! $this->validateBalanced($lines)) {
                throw new UnbalancedJournalException(
                    $this->calculateTotalDebits($lines),
                    $this->calculateTotalCredits($lines)
                );
            }

            // Find the accounting period for this entry date
            $period = AccountingPeriod::forDate($entryDate)->first();

            // Validate that the period is open (if period exists)
            if ($period && ! $period->isOpen()) {
                throw new ClosedPeriodException($period->period_code);
            }

            // Create entry in Posted status and immediately post to ledger
            $entry = JournalEntry::create([
                'entry_date' => $entryDate,
                'period_id' => $period?->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => 'Posted',
                'created_by' => $createdBy,
                'posted_by' => $createdBy,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'] ?? '0',
                    'credit' => $line['credit'] ?? '0',
                    'description' => $line['description'] ?? null,
                ]);
            }

            // Post directly to ledger - no approval workflow needed
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
                    'status' => 'Posted',
                ]
            );

            return $entry->fresh()->load('lines');
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
     * Calculate total debits from journal lines.
     *
     * @param  array  $lines  Array of journal line items
     * @return string Total debits as a string for precision
     */
    protected function calculateTotalDebits(array $lines): string
    {
        $total = '0';
        foreach ($lines as $line) {
            $total = $this->mathService->add($total, (string) ($line['debit'] ?? 0));
        }

        return $total;
    }

    /**
     * Calculate total credits from journal lines.
     *
     * @param  array  $lines  Array of journal line items
     * @return string Total credits as a string for precision
     */
    protected function calculateTotalCredits(array $lines): string
    {
        $total = '0';
        foreach ($lines as $line) {
            $total = $this->mathService->add($total, (string) ($line['credit'] ?? 0));
        }

        return $total;
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
        $reversedBy = $reversedBy ?? auth()->id();

        return DB::transaction(function () use ($originalEntry, $reason, $reversedBy) {
            // Re-fetch to ensure we see the latest committed state
            $originalEntry = JournalEntry::find($originalEntry->id);

            // Validation 1: Check if entry is already reversed
            if ($originalEntry->isReversed()) {
                throw new EntryAlreadyReversedException($originalEntry->id);
            }

            // Validation 2: Check if entry is posted (can only reverse posted entries)
            if (! $originalEntry->isPosted()) {
                throw new EntryNotPostedException($originalEntry->id);
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
                $reversedBy
            );

            // Note: Entry is already posted via createJournalEntry() - no approval needed for reversals

            // Update original entry status and create explicit link via reversal_id
            $originalEntry->update([
                'status' => 'Reversed',
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
        DB::transaction(function () use ($entry) {
            foreach ($entry->lines as $line) {
                $currentBalance = $this->getAccountBalance($line->account_code);

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
                    'entry_date' => $entry->entry_date,
                    'journal_entry_id' => $entry->id,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'running_balance' => $newBalance,
                ]);
            }

            $this->invalidateTrialBalanceCache();
        });
    }

    protected function invalidateTrialBalanceCache(): void
    {
        try {
            Cache::tags(['ledger', 'trial-balance'])->flush();
        } catch (\Exception $e) {
            // Tags not supported on this cache driver - cache will expire naturally
        }
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

        return in_array($account->account_type, ['Asset', 'Expense']);
    }

    /**
     * Get the current balance for an account.
     *
     * Retrieves the running balance from the most recent ledger entry,
     * optionally filtered by an as-of date.
     *
     * @param  string  $accountCode  The account code to query
     * @param  string|null  $asOfDate  Date in YYYY-MM-DD format (default: current date)
     * @param  int|null  $branchId  Optional branch ID to filter by
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
            $query->whereHas('journalEntry', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
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
        $entries = AccountLedger::where('account_code', $accountCode)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get();

        $totalDebits = '0';
        $totalCredits = '0';

        foreach ($entries as $entry) {
            $totalDebits = $this->mathService->add($totalDebits, (string) $entry->debit);
            $totalCredits = $this->mathService->add($totalCredits, (string) $entry->credit);
        }

        // For expense accounts, activity is typically the net amount (debits - credits)
        // This gives us the actual spending in the period
        return $this->mathService->subtract($totalDebits, $totalCredits);
    }

    /**
     * Update till balance after transaction.
     *
     * @param  string  $tillId  Till identifier
     * @param  string  $currencyCode  Currency code
     * @param  string  $type  Transaction type (Buy/Sell)
     * @param  string  $amountLocal  Local amount in MYR
     * @param  string  $amountForeign  Foreign currency amount
     */
    public function updateTillBalance(string $tillId, string $currencyCode, string $type, string $amountLocal, string $amountForeign): void
    {
        $tillBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', $currencyCode)
            ->where('date', today())
            ->whereNull('closed_at')
            ->first();

        if (! $tillBalance) {
            return;
        }

        $currentTotal = $tillBalance->transaction_total ?? '0';
        $foreignTotal = $tillBalance->foreign_total ?? '0';

        if ($type === TransactionType::Buy->value) {
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->add($foreignTotal, $amountForeign),
            ]);
        } else {
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->subtract($foreignTotal, $amountForeign),
            ]);
        }
    }

    /**
     * Create accounting journal entries for a transaction.
     *
     * @param  Transaction  $transaction  Transaction to create entries for
     * @return JournalEntry Created journal entry
     */
    public function createTransactionAccountingEntries(Transaction $transaction): JournalEntry
    {
        $entries = [];

        if ($transaction->type->isBuy()) {
            $entries = [
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Buy {$transaction->amount_foreign} {$transaction->currency_code} @ {$transaction->rate}",
                ],
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Payment for {$transaction->currency_code} purchase",
                ],
            ];
        } else {
            $position = CurrencyPosition::where('currency_code', $transaction->currency_code)
                ->where('till_id', $transaction->till_id)
                ->first();

            $avgCost = $position ? $position->avg_cost_rate : $transaction->rate;
            $costBasis = $this->mathService->multiply((string) $transaction->amount_foreign, $avgCost);
            $revenue = $this->mathService->subtract((string) $transaction->amount_local, $costBasis);
            $isGain = $this->mathService->compare($revenue, '0') >= 0;

            $entries = [
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Sale of {$transaction->amount_foreign} {$transaction->currency_code}",
                ],
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => '0',
                    'credit' => $costBasis,
                    'description' => "Cost of {$transaction->currency_code} sold",
                ],
            ];

            if ($isGain) {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_TRADING_REVENUE->value,
                    'debit' => '0',
                    'credit' => $revenue,
                    'description' => "Gain on {$transaction->currency_code} sale",
                ];
            } else {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_LOSS->value,
                    'debit' => $this->mathService->multiply($revenue, '-1'),
                    'credit' => '0',
                    'description' => "Loss on {$transaction->currency_code} sale",
                ];
            }
        }

        return $this->createJournalEntry(
            $entries,
            'Transaction',
            $transaction->id,
            "Transaction #{$transaction->id} - {$transaction->type->value} {$transaction->currency_code}"
        );
    }
}
