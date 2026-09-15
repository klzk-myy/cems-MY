<?php

namespace App\Services\Transaction;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Models\Alert;
use App\Models\FlaggedTransaction;
use App\Models\HighRiskCountry;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\ComplianceService;
use App\Services\Contracts\TransactionMonitoringServiceInterface;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionMonitoringService implements TransactionMonitoringServiceInterface
{
    protected ComplianceService $complianceService;

    protected MathService $mathService;

    public function __construct(
        ComplianceService $complianceService,
        MathService $mathService,
        protected AuditService $auditService,
        protected ThresholdService $thresholdService,
        protected AlertTriageService $alertTriageService
    ) {
        $this->complianceService = $complianceService;
        $this->mathService = $mathService;
    }

    public function monitorTransaction(Transaction $transaction): array
    {
        $result = DB::transaction(function () use ($transaction) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $flags = [];

            // Velocity check - 24h cumulative threshold
            $velocityCheck = $this->complianceService->checkVelocity(
                $lockedTransaction->customer_id,
                $lockedTransaction->amount_local
            );
            if ($velocityCheck['threshold_exceeded']) {
                $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::Velocity, "24h velocity exceeded: RM {$velocityCheck['with_new_transaction']}");
                // Calculate count before logging (avoid N+1)
                $transactionCount = Transaction::where('customer_id', $lockedTransaction->customer_id)
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count();
                $this->auditService->logAmlMonitorEvent('aml_velocity_alert_triggered', $lockedTransaction->id, [
                    'entity_type' => 'Transaction',
                    'new' => [
                        'customer_id' => $lockedTransaction->customer_id,
                        'velocity_amount' => $velocityCheck['with_new_transaction'],
                        'transaction_count' => $transactionCount,
                    ],
                ]);
            }

            // Structuring detection - multiple small transactions
            if ($this->complianceService->checkStructuring($lockedTransaction->customer_id)) {
                $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::Structuring, 'Potential structuring: 3+ transactions under RM '.number_format((float) $this->thresholdService->getStandardCddThreshold()).' within 1 hour');
                $this->auditService->logAmlMonitorEvent('aml_structuring_detected', $lockedTransaction->id, [
                    'entity_type' => 'Transaction',
                    'new' => [
                        'customer_id' => $lockedTransaction->customer_id,
                        'pattern' => 'aggregate_transactions',
                    ],
                ]);
            }

            // Aggregate transaction check - related transactions exceeding threshold
            $aggregateCheck = $this->complianceService->checkAggregateTransactions(
                $lockedTransaction->customer_id,
                $lockedTransaction->amount_local
            );
            if ($aggregateCheck['has_aggregate_concern']) {
                $flags[] = $this->createFlag(
                    $lockedTransaction,
                    ComplianceFlagType::LargeAmount,
                    "Aggregate concern: RM {$aggregateCheck['total_aggregate']} across {$aggregateCheck['transaction_count']} transactions in 24h"
                );
            }

            // Unusual pattern detection
            if ($this->isUnusualPattern($lockedTransaction)) {
                $deviationPct = (float) $this->thresholdService->get('monitoring', 'unusual_pattern_multiplier', 2) * 100;
                $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::ManualReview, "Transaction deviates {$deviationPct}% from customer average");
            }

            // High-risk country transaction
            if ($this->isHighRiskCountry($lockedTransaction)) {
                $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::HighRiskCountry, 'High-risk country transaction: '.$lockedTransaction->customer->nationality);
            }

            // Profile deviation check
            if ($this->isProfileDeviation($lockedTransaction)) {
                $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::ProfileDeviation, 'Transaction volume exceeds customer profile');
            }

            // Duration threshold check for large transactions on hold
            $durationCheck = $this->complianceService->checkTransactionDuration($lockedTransaction);
            if ($durationCheck['has_duration_concern']) {
                $flags[] = $this->createFlag(
                    $lockedTransaction,
                    ComplianceFlagType::EddRequired,
                    "Duration threshold exceeded: {$durationCheck['hours_on_hold']} hours on hold (threshold: {$durationCheck['threshold_hours']} hours) - {$durationCheck['severity']}"
                );
            }

            // Hold decision. A Completed transaction has already booked its
            // journal, position and till movements — reverting status to
            // PendingApproval without unwinding them corrupts the books and a
            // later approval would double-apply every effect. Keep the record
            // Completed and flag it for compliance review instead; voiding is a
            // separate reversal workflow.
            $holdCheck = $this->complianceService->requiresHold(
                $lockedTransaction->amount_local,
                $lockedTransaction->customer
            );
            if ($holdCheck->requiresHold
                && $lockedTransaction->status->isCompleted()
                && $lockedTransaction->approved_by === null) {
                foreach ($holdCheck->reasons as $reason) {
                    $flags[] = $this->createFlag($lockedTransaction, ComplianceFlagType::EddRequired, $reason);
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

        return $result;
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

    protected function isUnusualPattern(Transaction $transaction): bool
    {
        $lookbackDays = (int) $this->thresholdService->get('monitoring', 'unusual_pattern_lookback_days', 90);
        $multiplier = (string) $this->thresholdService->get('monitoring', 'unusual_pattern_multiplier', 2);

        $customerAvg = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->avg('amount_local');

        if (! $customerAvg || $this->mathService->compare((string) $customerAvg, '0') === 0) {
            return false;
        }

        $deviation = $this->mathService->divide(
            (string) $transaction->amount_local,
            (string) $customerAvg
        );

        return $this->mathService->compare($deviation, $multiplier) > 0;
    }

    protected function isHighRiskCountry(Transaction $transaction): bool
    {
        if (! $transaction->customer || ! $transaction->customer->nationality) {
            return false;
        }

        if ($this->mathService->compare($transaction->amount_local, $this->thresholdService->getStandardCddThreshold()) < 0) {
            return false;
        }

        return in_array($transaction->customer->nationality, HighRiskCountry::countryCodes(), true);
    }

    protected function isProfileDeviation(Transaction $transaction): bool
    {
        if (! $transaction->customer || ! $transaction->customer->annual_volume_estimate) {
            return false;
        }

        $annualEstimate = (string) $transaction->customer->annual_volume_estimate;

        if ($this->mathService->compare($annualEstimate, '0') <= 0) {
            return false;
        }

        $monthlyThreshold = $this->mathService->divide($annualEstimate, '12');
        $monthlyThreshold = $this->mathService->multiply($monthlyThreshold, '2');

        $startOfMonth = now()->startOfMonth();
        $currentMonthVolume = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', $startOfMonth)
            ->selectRaw('CAST(SUM(amount_local) AS CHAR) as total')
            ->value('total') ?? '0';

        return $this->mathService->compare((string) $currentMonthVolume, $monthlyThreshold) > 0;
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
