<?php

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * Import bank statement lines
     */
    public function importStatement(string $accountCode, array $lines, int $userId): array
    {
        return DB::transaction(function () use ($accountCode, $lines, $userId) {
            $imported = [];

            foreach ($lines as $line) {
                $record = BankReconciliation::create([
                    'account_code' => $accountCode,
                    'statement_date' => $line['date'],
                    'reference' => $line['reference'] ?? null,
                    'description' => $line['description'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'status' => 'unmatched',
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
                'unmatched' => BankReconciliation::where('account_code', $accountCode)
                    ->where('status', 'unmatched')
                    ->count(),
            ];
        });
    }

    /**
     * Create an outstanding check entry (check issued but not yet presented)
     *
     * @param  string  $accountCode  Cash/bank account code
     * @param  array  $checkData  Check details (check_number, check_date, check_payee, amount, etc.)
     * @param  int  $userId  User creating the entry
     */
    public function createOutstandingCheck(string $accountCode, array $checkData, int $userId): BankReconciliation
    {
        return BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $checkData['check_date'] ?? today(),
            'reference' => $checkData['check_number'],
            'description' => 'Check issued: '.($checkData['check_payee'] ?? 'Unknown payee'),
            'debit' => $checkData['amount'] ?? 0,
            'credit' => 0,
            'status' => 'unmatched',
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

        if ($record->check_status !== 'issued') {
            throw new \InvalidArgumentException("Check {$record->check_number} is not in 'issued' status.");
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

        if (! in_array($record->check_status, ['issued', 'presented'])) {
            throw new \InvalidArgumentException("Check {$record->check_number} cannot be cleared from '{$record->check_status}' status.");
        }

        $record->update([
            'check_status' => 'cleared',
            'status' => 'matched', // Auto-match when cleared
        ]);

        return $record;
    }

    /**
     * Stop a check (cancel the check)
     */
    public function stopCheck(int $reconciliationId, string $reason, int $userId): BankReconciliation
    {
        $record = BankReconciliation::findOrFail($reconciliationId);

        if ($record->check_status === 'cleared') {
            throw new \InvalidArgumentException("Check {$record->check_number} has already been cleared and cannot be stopped.");
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
            ->where('status', 'unmatched')
            ->get();

        foreach ($unmatched as $record) {
            // Skip checks - they are matched manually when presented/cleared
            if ($record->check_number !== null) {
                continue;
            }

            // Look for matching journal entry
            $amount = abs($record->getAmount());
            $isDebit = $record->getAmount() > 0;
            $column = $isDebit ? 'debit' : 'credit';

            $matchingEntry = JournalEntry::where('status', 'Posted')
                ->whereHas('lines', function ($query) use ($accountCode, $amount, $column) {
                    $query->where('account_code', $accountCode)
                        ->where($column, $amount);
                })
                ->whereDate('entry_date', $record->statement_date)
                ->first();

            if ($matchingEntry) {
                $record->update([
                    'status' => 'matched',
                    'matched_to_journal_entry_id' => $matchingEntry->id,
                    'matched_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get reconciliation report
     */
    public function getReconciliationReport(string $accountCode, string $fromDate, string $toDate): array
    {
        $statementBalance = BankReconciliation::where('account_code', $accountCode)
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get()
            ->sum(fn ($r) => $r->getAmount());

        $unmatchedItems = BankReconciliation::where('account_code', $accountCode)
            ->where('status', 'unmatched')
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get();

        $exceptions = BankReconciliation::where('account_code', $accountCode)
            ->where('status', 'exception')
            ->whereBetween('statement_date', [$fromDate, $toDate])
            ->get();

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
     */
    public function getReconciliationViewData(string $accountCode, string $fromDate, string $toDate): array
    {
        $rawReport = $this->getReconciliationReport($accountCode, $fromDate, $toDate);

        // Transform unmatched items to match view expectations
        $outstandingChecks = collect();
        $outstandingDeposits = collect();
        foreach ($rawReport['unmatched_items'] as $item) {
            $itemData = [
                'date' => $item->statement_date?->toDateString(),
                'reference' => $item->reference,
                'amount' => $item->getAmount(),
            ];
            if ($item->debit > 0) {
                $outstandingChecks->push($itemData);
            } else {
                $outstandingDeposits->push($itemData);
            }
        }

        return [
            'book_balance' => $rawReport['statement_balance'] ?? 0,
            'outstanding_checks' => $outstandingChecks->sum(fn ($i) => $i['amount']),
            'outstanding_deposits' => $outstandingDeposits->sum(fn ($i) => abs($i['amount'])),
            'adjusted_balance' => ($rawReport['statement_balance'] ?? 0)
                + $outstandingChecks->sum(fn ($i) => $i['amount'])
                - $outstandingDeposits->sum(fn ($i) => abs($i['amount'])),
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

        $issued = (clone $query)->where('check_status', 'issued')->get();
        $presented = (clone $query)->where('check_status', 'presented')->get();
        $cleared = (clone $query)->where('check_status', 'cleared')->get();
        $returned = (clone $query)->where('check_status', 'returned')->get();
        $stopped = (clone $query)->where('check_status', 'stopped')->get();

        return [
            'account_code' => $accountCode,
            'as_of_date' => $asOfDate,
            'issued' => [
                'count' => $issued->count(),
                'total' => $issued->sum(fn ($r) => $r->getAmount()),
                'items' => $issued,
            ],
            'presented' => [
                'count' => $presented->count(),
                'total' => $presented->sum(fn ($r) => $r->getAmount()),
                'items' => $presented,
            ],
            'cleared' => [
                'count' => $cleared->count(),
                'total' => $cleared->sum(fn ($r) => $r->getAmount()),
                'items' => $cleared,
            ],
            'returned' => [
                'count' => $returned->count(),
                'total' => $returned->sum(fn ($r) => $r->getAmount()),
                'items' => $returned,
            ],
            'stopped' => [
                'count' => $stopped->count(),
                'total' => $stopped->sum(fn ($r) => $r->getAmount()),
                'items' => $stopped,
            ],
            'total_outstanding' => $issued->sum(fn ($r) => $r->getAmount()) + $presented->sum(fn ($r) => $r->getAmount()),
        ];
    }

    /**
     * Get aging of outstanding checks
     *
     * Categorizes outstanding checks by how long they've been outstanding.
     */
    public function getChecksAgingReport(string $accountCode, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? today();
        $outstanding = BankReconciliation::where('account_code', $accountCode)
            ->whereNotNull('check_number')
            ->whereIn('check_status', ['issued', 'presented'])
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
                    'total' => $current->sum(fn ($r) => $r->getAmount()),
                    'items' => $current,
                ],
                'days_31_60' => [
                    'count' => $days30->count(),
                    'total' => $days30->sum(fn ($r) => $r->getAmount()),
                    'items' => $days30,
                ],
                'days_61_90' => [
                    'count' => $days60->count(),
                    'total' => $days60->sum(fn ($r) => $r->getAmount()),
                    'items' => $days60,
                ],
                'days_91_180' => [
                    'count' => $days90->count(),
                    'total' => $days90->sum(fn ($r) => $r->getAmount()),
                    'items' => $days90,
                ],
                'over_180' => [
                    'count' => $over90->count(),
                    'total' => $over90->sum(fn ($r) => $r->getAmount()),
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
            'status' => 'exception',
            'notes' => $reason,
        ]);

        return $record;
    }
}
