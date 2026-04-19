<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Journal Entry Workflow Service
 *
 * Handles the journal entry workflow: Draft → Pending → Posted.
 * Implements segregation of duties where creators cannot approve their own entries.
 */
class JournalEntryWorkflowService
{
    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Create a new JournalEntryWorkflowService instance.
     */
    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
    }

    /**
     * Create a draft journal entry.
     *
     * @param  array  $lines  Array of journal line items
     * @param  string  $description  Entry description
     * @param  string|null  $entryDate  Entry date in YYYY-MM-DD format
     * @param  int|null  $costCenterId  Cost center ID
     * @param  int|null  $departmentId  Department ID
     */
    public function createDraft(
        array $lines,
        string $description = '',
        ?string $entryDate = null,
        ?int $costCenterId = null,
        ?int $departmentId = null
    ): JournalEntry {
        return DB::transaction(function () use ($lines, $description, $entryDate, $costCenterId, $departmentId) {
            $entryDate = $entryDate ?? now()->toDateString();
            $userId = auth()->id();

            // Generate entry number
            $entryNumber = $this->generateEntryNumber($entryDate);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'period_id' => $this->getPeriodId($entryDate),
                'description' => $description,
                'status' => 'Draft',
                'created_by' => $userId,
                'cost_center_id' => $costCenterId,
                'department_id' => $departmentId,
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'journal_entry_draft_created',
                'entity_type' => 'JournalEntry',
                'entity_id' => $entry->id,
                'new_values' => ['description' => $description],
                'ip_address' => request()->ip(),
            ]);

            return $entry->fresh()->load('lines');
        });
    }

    /**
     * Submit a draft entry for approval.
     *
     * @throws \InvalidArgumentException
     */
    public function submitForApproval(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== JournalEntryStatus::Draft) {
            throw new \InvalidArgumentException('Only draft entries can be submitted for approval');
        }

        // Validate balanced
        if (! $this->validateBalanced($entry)) {
            throw new \InvalidArgumentException('Journal entry is not balanced');
        }

        $entry->update(['status' => 'Pending']);

        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'journal_entry_submitted',
            'entity_type' => 'JournalEntry',
            'entity_id' => $entry->id,
            'ip_address' => request()->ip(),
        ]);

        return $entry->fresh();
    }

    /**
     * Approve a pending entry and post it to the ledger.
     *
     * @param  string|null  $notes  Approval notes
     *
     * @throws \InvalidArgumentException
     */
    public function approve(JournalEntry $entry, ?string $notes = null): JournalEntry
    {
        $userId = auth()->id();
        $user = User::find($userId);

        // Check if user has approval permission (Manager or Admin)
        if (! $this->canApprove($user)) {
            throw new \InvalidArgumentException('User does not have permission to approve entries');
        }

        // Cannot approve own entries (segregation of duties)
        if ($entry->created_by === $userId) {
            throw new \InvalidArgumentException('Cannot approve your own journal entry');
        }

        if ($entry->status !== JournalEntryStatus::Pending) {
            throw new \InvalidArgumentException('Only pending entries can be approved');
        }

        return DB::transaction(function () use ($entry, $userId, $notes) {
            // Lock the journal entry row to prevent concurrent approvals
            $lockedEntry = JournalEntry::where('id', $entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check status after acquiring lock
            if ($lockedEntry->status !== JournalEntryStatus::Pending) {
                throw new \InvalidArgumentException('Journal entry is no longer pending; it may have been processed by another user');
            }

            // Cannot approve own entries (segregation of duties)
            if ($lockedEntry->created_by === $userId) {
                throw new \InvalidArgumentException('Cannot approve your own journal entry');
            }

            // Create ledger entries
            $this->createLedgerEntries($lockedEntry);

            // Update entry status
            $lockedEntry->update([
                'status' => 'Posted',
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $notes,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'journal_entry_approved',
                'entity_type' => 'JournalEntry',
                'entity_id' => $lockedEntry->id,
                'ip_address' => request()->ip(),
            ]);

            return $lockedEntry->fresh();
        });
    }

    /**
     * Reject a pending entry, returning it to draft status.
     *
     * @param  string  $notes  Rejection reason
     *
     * @throws \InvalidArgumentException
     */
    public function reject(JournalEntry $entry, string $notes): JournalEntry
    {
        $userId = auth()->id();
        $user = User::find($userId);

        if (! $this->canApprove($user)) {
            throw new \InvalidArgumentException('User does not have permission to reject entries');
        }

        if ($entry->status !== JournalEntryStatus::Pending) {
            throw new \InvalidArgumentException('Only pending entries can be rejected');
        }

        $entry->update([
            'status' => 'Draft',
            'approval_notes' => $notes,
        ]);

        SystemLog::create([
            'user_id' => $userId,
            'action' => 'journal_entry_rejected',
            'entity_type' => 'JournalEntry',
            'entity_id' => $entry->id,
            'new_values' => ['rejection_reason' => $notes],
            'ip_address' => request()->ip(),
        ]);

        return $entry->fresh();
    }

    /**
     * Post an entry directly (bypassing approval).
     * Used for system-generated entries or entries created by authorized posters.
     */
    public function postDirectly(JournalEntry $entry): JournalEntry
    {
        $userId = auth()->id();

        if ($entry->status !== JournalEntryStatus::Draft) {
            throw new \InvalidArgumentException('Only draft entries can be posted directly');
        }

        // Validate balanced
        if (! $this->validateBalanced($entry)) {
            throw new \InvalidArgumentException('Journal entry is not balanced');
        }

        return DB::transaction(function () use ($entry, $userId) {
            if (! $entry->relationLoaded('lines')) {
                $entry->load('lines');
            }

            $this->createLedgerEntries($entry);

            $entry->update([
                'status' => 'Posted',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'journal_entry_posted_direct',
                'entity_type' => 'JournalEntry',
                'entity_id' => $entry->id,
                'ip_address' => request()->ip(),
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Reverse a posted entry.
     */
    public function reverse(JournalEntry $entry, string $reason = ''): JournalEntry
    {
        $userId = auth()->id();

        if ($entry->status !== JournalEntryStatus::Posted) {
            throw new \InvalidArgumentException('Only posted entries can be reversed');
        }

        return DB::transaction(function () use ($entry, $reason, $userId) {
            if (! $entry->relationLoaded('lines')) {
                $entry->load('lines');
            }

            // Create reversal lines
            $reversalLines = [];
            foreach ($entry->lines as $line) {
                $reversalLines[] = [
                    'account_code' => $line->account_code,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => 'Reversal: '.($line->description ?? ''),
                ];
            }

            // Generate reversal entry number
            $reversalEntryNumber = $this->generateEntryNumber(now()->toDateString());

            $reversalEntry = JournalEntry::create([
                'entry_number' => $reversalEntryNumber,
                'entry_date' => now()->toDateString(),
                'period_id' => $this->getPeriodId(now()->toDateString()),
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'status' => 'Posted',
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            foreach ($reversalLines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'],
                ]);
            }

            // Create ledger entries for reversal
            foreach ($reversalEntry->lines as $line) {
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
                    'entry_date' => $reversalEntry->entry_date,
                    'journal_entry_id' => $reversalEntry->id,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'running_balance' => $newBalance,
                ]);
            }

            // Update original entry status
            $entry->update([
                'status' => 'Reversed',
                'reversed_by' => $userId,
                'reversed_at' => now(),
            ]);

            SystemLog::create([
                'user_id' => $userId,
                'action' => 'journal_entry_reversed',
                'entity_type' => 'JournalEntry',
                'entity_id' => $entry->id,
                'new_values' => [
                    'reversal_entry_id' => $reversalEntry->id,
                    'reason' => $reason,
                ],
                'ip_address' => request()->ip(),
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Check if user can approve journal entries.
     */
    protected function canApprove(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::Admin || $role === UserRole::Manager;
        }

        // Handle string role
        return in_array($role, ['admin', 'manager']);
    }

    /**
     * Generate a unique entry number.
     *
     * Format: JE-YYYYMM-XXXX
     */
    protected function generateEntryNumber(string $date): string
    {
        $prefix = 'JE-'.date('Ym', strtotime($date)).'-';
        $lastEntry = JournalEntry::where('entry_number', 'like', $prefix.'%')
            ->orderBy('entry_number', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->entry_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get accounting period ID for a date.
     */
    protected function getPeriodId(string $date): ?int
    {
        $period = AccountingPeriod::forDate($date)->first();

        return $period?->id;
    }

    /**
     * Validate that journal entry lines are balanced.
     */
    protected function validateBalanced(JournalEntry $entry): bool
    {
        $totalDebits = '0';
        $totalCredits = '0';

        foreach ($entry->lines as $line) {
            $totalDebits = $this->mathService->add($totalDebits, (string) $line->debit);
            $totalCredits = $this->mathService->add($totalCredits, (string) $line->credit);
        }

        return $this->mathService->compare($totalDebits, $totalCredits) === 0;
    }

    /**
     * Create ledger entries for a journal entry.
     */
    protected function createLedgerEntries(JournalEntry $entry): void
    {
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
    }

    /**
     * Get current balance for an account.
     */
    protected function getAccountBalance(string $accountCode): string
    {
        $lastEntry = AccountLedger::where('account_code', $accountCode)
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
            throw new \InvalidArgumentException("Account not found: {$accountCode}");
        }

        return in_array($account->account_type, ['Asset', 'Expense']);
    }
}
