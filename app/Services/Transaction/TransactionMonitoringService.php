<?php

namespace App\Services\Transaction;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Models\Alert;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Contracts\TransactionMonitoringServiceInterface;
use App\Services\Transaction\Checks\TransactionCheckRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionMonitoringService implements TransactionMonitoringServiceInterface
{
    public function __construct(
        protected TransactionCheckRegistry $checkRegistry,
        protected AuditService $auditService,
        protected AlertTriageService $alertTriageService
    ) {}

    /**
     * @return array{transaction_id: int, flags_created: int, flags: array<int, FlaggedTransaction>, status: TransactionStatus}
     */
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
        $flagModels = (new EloquentCollection($flags))
            ->filter(fn ($flag) => $flag instanceof FlaggedTransaction)
            ->reject(fn ($flag) => $flag->status?->isTerminal() ?? false)
            ->values();

        // One eager load for the whole batch and one probe for existing
        // alerts, instead of per-flag lazy loads + exists() queries.
        $flagModels->loadMissing(['customer', 'transaction']);
        $alertedFlagIds = Alert::whereIn('flagged_transaction_id', $flagModels->modelKeys())
            ->pluck('flagged_transaction_id')
            ->flip();

        foreach ($flagModels as $flag) {
            if ($alertedFlagIds->has($flag->id)) {
                continue;
            }

            try {
                $this->alertTriageService->createFromFlaggedTransaction($flag);
            } catch (\Throwable $e) {
                Log::error('Failed to create alert for flagged transaction', [
                    'flag_id' => $flag->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Existing flags of the same type for a transaction, live ones first.
     *
     * Terminal flags (resolved/rejected) are included — ordered after live
     * ones — so a reviewed-and-cleared suspicion is not resurrected as a
     * fresh flag every time monitoring re-runs (e.g. at approval while the
     * underlying pattern, such as structuring, is still inside its detection
     * window).
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  ComplianceFlagType  $flagType  The flag type to check for
     * @return EloquentCollection<int, FlaggedTransaction>
     */
    protected function existingFlags(Transaction $transaction, ComplianceFlagType $flagType): EloquentCollection
    {
        return FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', $flagType)
            ->orderByRaw(
                'CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END ASC',
                array_map(fn ($s) => $s->value, FlagStatus::terminalStatuses())
            )
            ->orderBy('id')
            ->get();
    }

    protected function createFlag(Transaction $transaction, ComplianceFlagType $type, string $reason): FlaggedTransaction
    {
        // Check for existing flags of same type — a materially identical
        // suspicion dedupes to its flag (live or dispositioned) instead of
        // creating a duplicate.
        $existingFlags = $this->existingFlags($transaction, $type);

        foreach ($existingFlags as $existingFlag) {
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
        }

        if ($existingFlags->isNotEmpty()) {
            // Different reason (<80% similarity): a materially different
            // suspicion is a separate finding — append a new flag rather
            // than rewriting the existing one's reason/status, which would
            // destroy the audit trail of the original suspicion.
            $existingFlag = $existingFlags->first();
            $existingReason = $existingFlag->flag_reason;

            Log::info('Distinct AML flag reason — creating additional flag', [
                'transaction_id' => $transaction->id,
                'flag_type' => $type->value,
                'existing_flag_id' => $existingFlag->id,
                'existing_reason' => $existingReason,
                'new_reason' => $reason,
            ]);

            $this->auditService->logAmlMonitorEvent('aml_flag_distinct_reason', $transaction->id, [
                'entity_type' => 'Transaction',
                'old' => [
                    'flag_reason' => $existingReason,
                    'existing_flag_id' => $existingFlag->id,
                ],
                'new' => [
                    'flag_reason' => $reason,
                ],
            ]);
        }

        // No matching suspicion on file — record it as a new flag
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
