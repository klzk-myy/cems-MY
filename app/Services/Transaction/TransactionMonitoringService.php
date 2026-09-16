<?php

namespace App\Services\Transaction;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Models\Alert;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Contracts\TransactionMonitoringServiceInterface;
use App\Services\Transaction\Checks\TransactionCheckRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionMonitoringService implements TransactionMonitoringServiceInterface
{
    public function __construct(
        protected TransactionCheckRegistry $checkRegistry,
        protected AuditService $auditService,
        protected AlertTriageService $alertTriageService
    ) {}

    public function monitorTransaction(Transaction $transaction): array
    {
        return DB::transaction(function () use ($transaction) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $flags = [];

            foreach ($this->checkRegistry->checks() as $check) {
                foreach ($check->check($lockedTransaction) as $descriptor) {
                    $flags[] = $this->createFlag($lockedTransaction, $descriptor->type, $descriptor->reason);

                    if ($descriptor->auditEvent !== null) {
                        $this->auditService->logAmlMonitorEvent($descriptor->auditEvent, $lockedTransaction->id, [
                            'entity_type' => 'Transaction',
                            'new' => $descriptor->auditPayload,
                        ]);
                    }
                }
            }

            $this->createAlertsForFlags($flags);

            return [
                'transaction_id' => $lockedTransaction->id,
                'flags_created' => count($flags),
                'flags' => $flags,
                'status' => $lockedTransaction->status,
            ];
        });
    }

    /**
     * Ensure every monitoring flag has a corresponding alert for the triage queue.
     *
     * Runs inside the monitoring transaction (plan §1.1 step 4): flag and alert
     * are atomic, so a flag can never persist without its triage alert and a
     * failed alert rolls the flag back with it. Flags with an existing alert
     * are skipped; a unique index on alerts.flagged_transaction_id makes the
     * check-and-create race-free.
     *
     * @param  array<int, FlaggedTransaction>  $flags
     */
    protected function createAlertsForFlags(array $flags): void
    {
        foreach ($flags as $flag) {
            if (! $flag instanceof FlaggedTransaction) {
                continue;
            }

            try {
                $hasAlert = Alert::where('flagged_transaction_id', $flag->id)->exists();

                if (! $hasAlert) {
                    $this->alertTriageService->createFromFlaggedTransaction($flag);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to create alert for flagged transaction', [
                    'flag_id' => $flag->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check for existing flags of the same type for a transaction.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  ComplianceFlagType  $flagType  The flag type to check for
     * @return FlaggedTransaction|null Existing flag or null if none found
     */
    protected function checkExistingFlags(Transaction $transaction, ComplianceFlagType $flagType): ?FlaggedTransaction
    {
        return FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', $flagType)
            ->where('status', '!=', FlagStatus::Resolved)
            ->first();
    }

    protected function createFlag(Transaction $transaction, ComplianceFlagType $type, string $reason): FlaggedTransaction
    {
        // Check for existing flag of same type
        $existingFlag = $this->checkExistingFlags($transaction, $type);

        if ($existingFlag) {
            // Check if reason differs significantly using similarity comparison
            $existingReason = $existingFlag->flag_reason;
            $similarity = 0;
            similar_text($existingReason, $reason, $similarity);

            if ($similarity > 80) {
                // Very similar reason (>80%), skip creating duplicate
                Log::info('Prevented duplicate AML flag', [
                    'transaction_id' => $transaction->id,
                    'flag_type' => $type->value,
                    'existing_reason' => $existingReason,
                    'new_reason' => $reason,
                    'similarity' => $similarity,
                ]);

                $this->auditService->logAmlMonitorEvent('aml_flag_duplicate_prevented', $transaction->id, [
                    'entity_type' => 'Transaction',
                    'new' => [
                        'flag_type' => $type->value,
                        'similarity' => $similarity,
                        'existing_flag_id' => $existingFlag->id,
                    ],
                ]);

                return $existingFlag;
            }

            // Different reason (<80% similarity), update existing flag
            $existingFlag->update([
                'flag_reason' => $reason,
                'status' => FlagStatus::Open,
            ]);

            Log::info('Updated existing AML flag with new reason', [
                'transaction_id' => $transaction->id,
                'flag_type' => $type->value,
                'flag_id' => $existingFlag->id,
                'old_reason' => $existingReason,
                'new_reason' => $reason,
                'similarity' => $similarity,
            ]);

            $this->auditService->logAmlMonitorEvent('aml_flag_updated', $transaction->id, [
                'entity_type' => 'Transaction',
                'old' => [
                    'flag_reason' => $existingReason,
                ],
                'new' => [
                    'flag_reason' => $reason,
                    'similarity' => $similarity,
                ],
            ]);

            return $existingFlag;
        }

        // No existing flag, create new one
        $flag = FlaggedTransaction::create([
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'flag_type' => $type,
            'flag_reason' => $reason,
            'status' => FlagStatus::Open,
        ]);

        Log::info('Created new AML flag', [
            'transaction_id' => $transaction->id,
            'flag_type' => $type->value,
            'flag_id' => $flag->id,
        ]);

        $this->auditService->logAmlMonitorEvent('aml_flag_created', $transaction->id, [
            'entity_type' => 'Transaction',
            'new' => [
                'flag_id' => $flag->id,
                'flag_type' => $type->value,
                'customer_id' => $transaction->customer_id,
            ],
        ]);

        return $flag;
    }

    public function getOpenFlags(): array
    {
        return FlaggedTransaction::where('status', FlagStatus::Open)
            ->with(['transaction.customer', 'assignedTo'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    public function assignFlag(int $flagId, int $userId): bool
    {
        $flag = FlaggedTransaction::find($flagId);

        $updated = (bool) FlaggedTransaction::where('id', $flagId)
            ->update([
                'assigned_to' => $userId,
                'status' => FlagStatus::UnderReview,
            ]);

        if ($updated && $flag) {
            $this->auditService->logAmlMonitorEvent('aml_flag_assigned', $flag->transaction_id, [
                'entity_type' => 'FlaggedTransaction',
                'new' => [
                    'flag_id' => $flagId,
                    'assigned_to' => $userId,
                ],
            ]);
        }

        return $updated;
    }

    public function resolveFlag(int $flagId, int $userId, ?string $notes = null): bool
    {
        $flag = FlaggedTransaction::find($flagId);

        $updated = (bool) FlaggedTransaction::where('id', $flagId)
            ->update([
                'reviewed_by' => $userId,
                'notes' => $notes,
                'status' => FlagStatus::Resolved,
                'resolved_at' => now(),
            ]);

        if ($updated && $flag) {
            $this->auditService->logAmlMonitorEvent('aml_flag_resolved', $flag->transaction_id, [
                'entity_type' => 'FlaggedTransaction',
                'new' => [
                    'flag_id' => $flagId,
                    'resolved_by' => $userId,
                    'notes' => $notes,
                ],
            ]);
        }

        return $updated;
    }
}
