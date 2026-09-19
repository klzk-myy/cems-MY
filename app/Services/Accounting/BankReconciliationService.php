<?php

namespace App\Services\Accounting;

use App\Enums\BankReconciliationStatus;
use App\Enums\CheckStatus;
use App\Enums\JournalEntryStatus;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ReconciliationItem array{id: int, date: string|null, reference: string|null, description: string, debit: string, credit: string, amount_myr: string, status: BankReconciliationStatus, notes: string|null}
 */
class BankReconciliationService
{
    public function __construct(
        protected MathService $mathService
    ) {}

    /**
     * Import bank statement lines
     */
    public function importStatement(string $accountCode, array $lines, int $userId): array
    {
        return DB::transaction(function () use ($accountCode, $lines, $userId) {
            $imported = [];
            $skipped = 0;

            foreach ($lines as $line) {
                // Re-importing the same statement must not create duplicates —
                // a line is identical when every supplied field matches an
                // existing record for this account.
                $exists = BankReconciliation::where('account_code', $accountCode)
                    ->whereDate('statement_date', $line['date'])
                    ->where('description', $line['description'])
                    ->where('debit', $line['debit'] ?? 0)
                    ->where('credit', $line['credit'] ?? 0)
                    ->when(
                        ($line['reference'] ?? null) !== null,
                        fn ($q) => $q->where('reference', $line['reference']),
                        fn ($q) => $q->whereNull('reference')
                    )
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $record = BankReconciliation::create([
                    'account_code' => $accountCode,
                    'statement_date' => $line['date'],
                    'reference' => $line['reference'] ?? null,
                    'description' => $line['description'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'status' => BankReconciliationStatus::Unmatched->value,
                    'created_by' => $userId,
                    'check_number' => $line['check_number'] ?? null,
                    'check_date' => $line['check_date'] ?? null,
                    'check_status' => $line['check_status'] ?? null,
                    'check_payee' => $line['check_payee'] ?? null,
                ]);

                $imported[] = $record;
            }

            $this->autoMatch($accountCode);

            return [
                'imported' => count($imported),
                'skipped' => $skipped,
                'unmatched' => BankReconciliation::where('account_code', $accountCode)
                    ->where('status', BankReconciliationStatus::Unmatched->value)
                    ->count(),
            ];
        });
    }

    /**
     * Create an outstanding check entry (check issued but not yet presented)
     *
     * @param  string  $accountCode  Cash/bank account code
     * @param  array  $checkData  Check details (check_number, check_date, check_payee, amount_myr, etc.)
     * @param  int  $userId  User creating the entry
     */
    public function createOutstandingCheck(string $accountCode, array $checkData, int $userId): BankReconciliation
    {
        return BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $checkData['check_date'] ?? today(),
            'reference' => $checkData['check_number'],
            'description' => 'Check issued: '.($checkData['check_payee'] ?? 'Unknown payee'),
            'debit' => $checkData['amount_myr'] ?? 0,
            'credit' => 0,
            'status' => BankReconciliationStatus::Unmatched->value,
            'created_by' => $userId,
            'check_number' => $checkData['check_number'],
            'check_date' => $checkData['check_date'] ?? today(),
            'check_status' => 'issued',
            'check_payee' => $checkData['check_payee'] ?? null,
        ]);
    }

    /**
     * Present a check (mark as presented for payment)
     */
    public function presentCheck(int $reconciliationId, ?string $presentedDate = null): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        if ($record->check_status !== CheckStatus::Issued) {
            throw new AccountingPeriodException("Check {$record->check_number} is not in 'issued' status.");
        }

        $record->update([
            'check_status' => 'presented',
        ]);

        return $record;
    }

    /**
     * Clear a check (mark as settled by bank)
     */
    public function clearCheck(int $reconciliationId, string $clearedDate): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        if (! in_array($record->check_status, [CheckStatus::Issued, CheckStatus::Presented])) {
            throw new AccountingPeriodException("Check {$record->check_number} cannot be cleared from '{$record->check_status?->value}' status.");
        }

        $record->update([
            'check_status' => 'cleared',
            'status' => BankReconciliationStatus::Matched->value, // Auto-match when cleared
        ]);

        return $record;
    }

    /**
     * Stop a check (cancel the check)
     */
    public function stopCheck(int $reconciliationId, string $reason, int $userId): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        if ($record->check_status === CheckStatus::Cleared) {
            throw new AccountingPeriodException("Check {$record->check_number} has already been cleared and cannot be stopped.");
        }

        $record->update([
            'check_status' => 'stopped',
            'notes' => $record->notes ? $record->notes."; Stopped: {$reason}" : "Stopped: {$reason}",
        ]);

        return $record;
    }

    /**
     * Return a check (e.g., insufficient funds)
     */
    public function returnCheck(int $reconciliationId, string $reason): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        $record->update([
            'check_status' => 'returned',
            'notes' => $record->notes ? $record->notes."; Returned: {$reason}" : "Returned: {$reason}",
        ]);

        return $record;
    }

    /**
     * Auto-match statement lines to journal entries
     */
    public function autoMatch(string $accountCode): void
    {
        $unmatched = BankReconciliation::where('account_code', $accountCode)
            ->where('status', BankReconciliationStatus::Unmatched->value)
            ->get();

        foreach ($unmatched as $record) {
            // Skip checks - they are matched manually when presented/cleared
            if ($record->check_number !== null) {
                continue;
            }

            // Look for matching journal entry. Keep the amount a BCMath string:
            // float coercion here would bind a rounded value against decimal
            // columns and silently miss exact matches.
            $recordAmount = $record->getAmount();
            $amountMyr = $this->mathService->abs($recordAmount);
            $isDebit = $this->mathService->compare($recordAmount, '0') > 0;
            $column = $isDebit ? 'debit' : 'credit';

            $matchingEntry = JournalEntry::where('status', JournalEntryStatus::Posted->value)
                ->whereHas('lines', function ($query) use ($accountCode, $amountMyr, $column) {
                    $query->where('account_code', $accountCode)
                        ->where($column, $amountMyr);
                })
                // A journal entry already matched to another statement line
                // must not be claimed again — without this, two identical-
                // amount lines both matched the same entry.
                ->whereNotIn('id', BankReconciliation::whereNotNull('matched_to_journal_entry_id')
                    ->select('matched_to_journal_entry_id'))
                ->whereDate('entry_date', Carbon::parse($record->statement_date)->toDateString())
                ->first();

            if ($matchingEntry) {
                $record->update([
                    'status' => BankReconciliationStatus::Matched->value,
                    'matched_to_journal_entry_id' => $matchingEntry->id,
                    'matched_at' => now(),
                ]);
            }
        }
    }

    /**
     * Sum reconciliation record amounts with BCMath (never float).
     *
     * @param  iterable<BankReconciliation>  $records
     */
    protected function sumAmounts(iterable $records): string
    {
        $total = '0';

        foreach ($records as $record) {
            $total = $this->mathService->add($total, $record->getAmount());
        }

        return $total;
    }

    /**
     * Unmatched statement lines for an account within a period.
     *
     * The single unmatched-items query behind the index view, the report,
     * and the export — every consumer derives from this set so the three
     * can never drift apart.
     *
     * @return Collection<int, BankReconciliation>
     */
    protected function unmatchedItems(string $accountCode, string $fromDate, string $toDate): Collection
    {
        return BankReconciliation::where('account_code', $accountCode)
            ->unmatched()
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get();
    }

    /**
     * Exception-flagged statement lines for an account within a period.
     *
     * @return Collection<int, BankReconciliation>
     */
    protected function exceptionItems(string $accountCode, string $fromDate, string $toDate): Collection
    {
        return BankReconciliation::where('account_code', $accountCode)
            ->exceptions()
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get();
    }

    /**
     * Normalize one statement line into the row shape every reconciliation
     * consumer renders. Money and dates are flattened to scalars; the
     * status enum is kept for badge display.
     *
     * @return ReconciliationItem
     */
    protected function normalizeItem(BankReconciliation $item): array
    {
        /** @var ReconciliationItem $row */
        $row = [
            'id' => $item->id,
            'date' => $item->statement_date?->toDateString(),
            'reference' => $item->reference,
            'description' => $item->description,
            'debit' => (string) $item->debit,
            'credit' => (string) $item->credit,
            'amount_myr' => $item->getAmount(),
            'status' => $item->status,
            'notes' => $item->notes,
        ];

        return $row;
    }

    /**
     * Get reconciliation report
     *
     * @return array{account_code: string, period: array{from: string, to: string}, statement_balance: string, unmatched_count: int, unmatched_items: iterable<int, ReconciliationItem>, exception_count: int, exceptions: iterable<int, ReconciliationItem>}
     */
    public function getReconciliationReport(string $accountCode, string $fromDate, string $toDate): array
    {
        $statementRecords = BankReconciliation::where('account_code', $accountCode)
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get();

        $statementBalance = $this->sumAmounts($statementRecords);

        $unmatchedItems = $this->unmatchedItems($accountCode, $fromDate, $toDate)
            ->map(fn (BankReconciliation $item) => $this->normalizeItem($item));

        $exceptions = $this->exceptionItems($accountCode, $fromDate, $toDate)
            ->map(fn (BankReconciliation $item) => $this->normalizeItem($item));

        return [
            'account_code' => $accountCode,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'statement_balance' => $statementBalance,
            'unmatched_count' => $unmatchedItems->count(),
            'unmatched_items' => $unmatchedItems,
            'exception_count' => $exceptions->count(),
            'exceptions' => $exceptions,
        ];
    }

    /**
     * Get reconciliation report transformed for view consumption.
     *
     * Transforms raw report data into format expected by the reconciliation view.
     *
     * @return array{book_balance: string, outstanding_checks: string, outstanding_deposits: string, adjusted_balance: string, outstanding_checks_list: array<int, ReconciliationItem>, outstanding_deposits_list: array<int, ReconciliationItem>}
     */
    public function getReconciliationViewData(string $accountCode, string $fromDate, string $toDate): array
    {
        $rawReport = $this->getReconciliationReport($accountCode, $fromDate, $toDate);

        // Partition the normalized unmatched rows: unmatched debits are
        // outstanding checks, unmatched credits are deposits in transit.
        /** @var Collection<int, ReconciliationItem> $outstandingChecks */
        $outstandingChecks = collect();
        /** @var Collection<int, ReconciliationItem> $outstandingDeposits */
        $outstandingDeposits = collect();
        foreach ($rawReport['unmatched_items'] as $item) {
            if ($this->mathService->compare($item['debit'], '0') > 0) {
                $outstandingChecks->push($item);
            } else {
                $outstandingDeposits->push($item);
            }
        }

        $statementBalance = (string) ($rawReport['statement_balance'] ?? '0');
        // Accumulate with BCMath; the previous ltrim('-', ...) sign strip turned
        // negative (reversal) deposits into positives and corrupted the balance.
        $checks = $outstandingChecks->reduce(
            fn (string $carry, array $item) => $this->mathService->add($carry, (string) $item['amount_myr']),
            '0'
        );
        $deposits = $outstandingDeposits->reduce(
            fn (string $carry, array $item) => $this->mathService->add($carry, (string) $item['amount_myr']),
            '0'
        );

        $adjustedBalance = $this->mathService->subtract(
            $this->mathService->add($statementBalance, $checks),
            $deposits
        );

        return [
            'book_balance' => $rawReport['statement_balance'] ?? '0',
            'outstanding_checks' => $checks,
            'outstanding_deposits' => $deposits,
            'adjusted_balance' => $adjustedBalance,
            'outstanding_checks_list' => $outstandingChecks->toArray(),
            'outstanding_deposits_list' => $outstandingDeposits->toArray(),
        ];
    }

    /**
     * Get outstanding checks report
     *
     * Returns checks that have been issued but not yet cleared,
     * categorized by their status.
     */
    public function getOutstandingChecksReport(string $accountCode, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? today()->toDateString();

        $query = BankReconciliation::where('account_code', $accountCode)
            ->whereNotNull('check_number')
            ->where('check_date', '<=', $asOfDate);

        $issued = (clone $query)->where('check_status', CheckStatus::Issued)->get();
        $presented = (clone $query)->where('check_status', CheckStatus::Presented)->get();
        $cleared = (clone $query)->where('check_status', CheckStatus::Cleared)->get();
        $returned = (clone $query)->where('check_status', CheckStatus::Returned)->get();
        $stopped = (clone $query)->where('check_status', CheckStatus::Stopped)->get();

        return [
            'account_code' => $accountCode,
            'as_of_date' => $asOfDate,
            'issued' => [
                'count' => $issued->count(),
                'total' => $this->sumAmounts($issued),
                'items' => $issued,
            ],
            'presented' => [
                'count' => $presented->count(),
                'total' => $this->sumAmounts($presented),
                'items' => $presented,
            ],
            'cleared' => [
                'count' => $cleared->count(),
                'total' => $this->sumAmounts($cleared),
                'items' => $cleared,
            ],
            'returned' => [
                'count' => $returned->count(),
                'total' => $this->sumAmounts($returned),
                'items' => $returned,
            ],
            'stopped' => [
                'count' => $stopped->count(),
                'total' => $this->sumAmounts($stopped),
                'items' => $stopped,
            ],
            'total_outstanding' => $this->mathService->add(
                $this->sumAmounts($issued),
                $this->sumAmounts($presented)
            ),
        ];
    }

    /**
     * Get aging of outstanding checks
     *
     * Categorizes outstanding checks by how long they've been outstanding.
     */
    public function getChecksAgingReport(string $accountCode, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : today();
        $outstanding = BankReconciliation::where('account_code', $accountCode)
            ->whereNotNull('check_number')
            ->whereIn('check_status', [CheckStatus::Issued, CheckStatus::Presented])
            ->where('check_date', '<=', $asOfDate)
            ->get();

        $current = collect();
        $days30 = collect();
        $days60 = collect();
        $days90 = collect();
        $over90 = collect();

        foreach ($outstanding as $check) {
            $daysOutstanding = $check->check_date->diffInDays($asOfDate);

            if ($daysOutstanding <= 30) {
                $current->push($check);
            } elseif ($daysOutstanding <= 60) {
                $days30->push($check);
            } elseif ($daysOutstanding <= 90) {
                $days60->push($check);
            } elseif ($daysOutstanding <= 180) {
                $days90->push($check);
            } else {
                $over90->push($check);
            }
        }

        return [
            'account_code' => $accountCode,
            'as_of_date' => $asOfDate->toDateString(),
            'aging' => [
                'current_0_30' => [
                    'count' => $current->count(),
                    'total' => $this->sumAmounts($current),
                    'items' => $current,
                ],
                'days_31_60' => [
                    'count' => $days30->count(),
                    'total' => $this->sumAmounts($days30),
                    'items' => $days30,
                ],
                'days_61_90' => [
                    'count' => $days60->count(),
                    'total' => $this->sumAmounts($days60),
                    'items' => $days60,
                ],
                'days_91_180' => [
                    'count' => $days90->count(),
                    'total' => $this->sumAmounts($days90),
                    'items' => $days90,
                ],
                'over_180' => [
                    'count' => $over90->count(),
                    'total' => $this->sumAmounts($over90),
                    'items' => $over90,
                ],
            ],
        ];
    }

    /**
     * Mark as exception with note
     */
    public function markAsException(int $reconciliationId, string $reason, int $userId): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        $record->update([
            'status' => BankReconciliationStatus::Exception->value,
            'notes' => $reason,
        ]);

        return $record;
    }

    /**
     * Manually match a reconciliation record to a journal entry.
     */
    public function manualMatch(int $reconciliationId, int $journalEntryId): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);
        $record->markMatched($journalEntryId);

        return $record;
    }

    /**
     * Unmatch a reconciliation record (revert to unmatched).
     */
    public function unmatch(int $reconciliationId): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);
        $record->markUnmatched();

        return $record;
    }
}
