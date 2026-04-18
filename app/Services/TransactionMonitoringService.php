<?php

namespace App\Services;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionMonitoringService
{
    protected ComplianceService $complianceService;

    protected MathService $mathService;

    public function __construct(
        ComplianceService $complianceService,
        MathService $mathService,
        protected AuditService $auditService
    ) {
        $this->complianceService = $complianceService;
        $this->mathService = $mathService;
    }

    public function monitorTransaction(Transaction $transaction): array
    {
        return DB::transaction(function () use ($transaction) {
            $flags = [];

            // Velocity check - 24h cumulative threshold
            $velocityCheck = $this->complianceService->checkVelocity(
                $transaction->customer_id,
                $transaction->amount_local
            );
            if ($velocityCheck['threshold_exceeded']) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::Velocity, "24h velocity exceeded: RM {$velocityCheck['with_new_transaction']}");
                $this->auditService->logAmlMonitorEvent('aml_velocity_alert_triggered', $transaction->id, [
                    'entity_type' => 'Transaction',
                    'new' => [
                        'customer_id' => $transaction->customer_id,
                        'velocity_amount' => $velocityCheck['with_new_transaction'],
                        'transaction_count' => Transaction::where('customer_id', $transaction->customer_id)
                            ->where('created_at', '>=', now()->subHours(24))
                            ->count(),
                    ],
                ]);
            }

            // Structuring detection - multiple small transactions
            if ($this->complianceService->checkStructuring($transaction->customer_id)) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::Structuring, 'Potential structuring: 3+ transactions under RM '.number_format((float) $this->complianceService::STANDARD_CDD_THRESHOLD).' within 1 hour');
                $this->auditService->logAmlMonitorEvent('aml_structuring_detected', $transaction->id, [
                    'entity_type' => 'Transaction',
                    'new' => [
                        'customer_id' => $transaction->customer_id,
                        'pattern' => 'aggregate_transactions',
                    ],
                ]);
            }

            // Aggregate transaction check - related transactions exceeding threshold
            $aggregateCheck = $this->complianceService->checkAggregateTransactions(
                $transaction->customer_id,
                $transaction->amount_local
            );
            if ($aggregateCheck['has_aggregate_concern']) {
                $flags[] = $this->createFlag(
                    $transaction,
                    ComplianceFlagType::LargeAmount,
                    "Aggregate concern: RM {$aggregateCheck['total_aggregate']} across {$aggregateCheck['transaction_count']} transactions in 24h"
                );
            }

            // Unusual pattern detection
            if ($this->isUnusualPattern($transaction)) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::ManualReview, 'Transaction deviates 200% from customer average');
            }

            // High-risk country transaction
            if ($this->isHighRiskCountry($transaction)) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::HighRiskCountry, 'High-risk country transaction: '.$transaction->customer->nationality);
            }

            // Round amount detection
            if ($this->isRoundAmount($transaction)) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::RoundAmount, 'Round amount transaction - review purpose: RM '.$transaction->amount_local);
            }

            // Profile deviation check
            if ($this->isProfileDeviation($transaction)) {
                $flags[] = $this->createFlag($transaction, ComplianceFlagType::ProfileDeviation, 'Transaction volume exceeds customer profile');
            }

            // Duration threshold check for large transactions on hold
            $durationCheck = $this->complianceService->checkTransactionDuration($transaction);
            if ($durationCheck['has_duration_concern']) {
                $flags[] = $this->createFlag(
                    $transaction,
                    ComplianceFlagType::EddRequired,
                    "Duration threshold exceeded: {$durationCheck['hours_on_hold']} hours on hold (threshold: {$durationCheck['threshold_hours']} hours) - {$durationCheck['severity']}"
                );
            }

            // Hold decision
            $holdCheck = $this->complianceService->requiresHold(
                $transaction->amount_local,
                $transaction->customer
            );
            if ($holdCheck['requires_hold']
                && $transaction->status->isCompleted()
                && $transaction->approved_by === null) {
                $transaction->update(['status' => TransactionStatus::PendingApproval]);
                foreach ($holdCheck['reasons'] as $reason) {
                    $flags[] = $this->createFlag($transaction, ComplianceFlagType::EddRequired, $reason);
                }
            }

            return [
                'transaction_id' => $transaction->id,
                'flags_created' => count($flags),
                'flags' => $flags,
                'status' => $transaction->status,
            ];
        });
    }

    protected function isUnusualPattern(Transaction $transaction): bool
    {
        $customerAvg = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', now()->subDays(90))
            ->avg('amount_local');

        if (! $customerAvg || (float) $customerAvg === 0.0) {
            return false;
        }

        $deviation = $this->mathService->divide(
            (string) $transaction->amount_local,
            (string) $customerAvg
        );

        return $this->mathService->compare($deviation, '2') > 0;
    }

    protected function isHighRiskCountry(Transaction $transaction): bool
    {
        if (! $transaction->customer || ! $transaction->customer->nationality) {
            return false;
        }

        if ($this->mathService->compare($transaction->amount_local, $this->complianceService::STANDARD_CDD_THRESHOLD) < 0) {
            return false;
        }

        $highRiskCountries = DB::table('high_risk_countries')
            ->pluck('country_name')
            ->toArray();

        return in_array($transaction->customer->nationality, $highRiskCountries, true);
    }

    protected function isRoundAmount(Transaction $transaction): bool
    {
        if ($this->mathService->compare($transaction->amount_local, $this->complianceService::CTOS_THRESHOLD) < 0) {
            return false;
        }

        $remainder = bcmod((string) $transaction->amount_local, $this->complianceService::CTOS_THRESHOLD);

        return $this->mathService->compare($remainder, '0') === 0;
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
            'flag_type' => $type,
            'flag_reason' => $reason,
            'status' => FlagStatus::Open,
        ]);

        Log::info('Created new AML flag', [
            'transaction_id' => $transaction->id,
            'flag_type' => $type->value,
            'flag_id' => $flag->id,
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
        return FlaggedTransaction::where('id', $flagId)
            ->update([
                'assigned_to' => $userId,
                'status' => FlagStatus::UnderReview,
            ]);
    }

    public function resolveFlag(int $flagId, int $userId, ?string $notes = null): bool
    {
        return FlaggedTransaction::where('id', $flagId)
            ->update([
                'reviewed_by' => $userId,
                'notes' => $notes,
                'status' => FlagStatus::Resolved,
                'resolved_at' => now(),
            ]);
    }
}
