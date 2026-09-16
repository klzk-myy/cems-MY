<?php

namespace App\Jobs;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\CustomerScreeningService;
use App\Services\ThresholdService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplianceScreeningJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $customerId) {}

    public function handle(CustomerScreeningService $service, ThresholdService $thresholdService, AuditService $auditService): void
    {
        $start = microtime(true);

        $customer = Customer::find($this->customerId);
        if ($customer) {
            try {
                $this->screenAndEnforce($service, $auditService, $customer);
            } catch (\Throwable $e) {
                // A screening failure must be logged and swallowed: this job
                // runs on the shared compliance queue and a thrown exception
                // would retry the hold/alert side effects on every attempt.
                Log::error('Compliance screening failed', [
                    'customer_id' => $this->customerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $durationMs = (microtime(true) - $start) * 1000;

        Log::info('Compliance screening job completed', [
            'customer_id' => $this->customerId,
            'duration_ms' => $durationMs,
        ]);

        $threshold = (float) $thresholdService->getJobDurationWarning();
        if ($durationMs > $threshold) {
            Log::warning('Slow compliance screening job', [
                'customer_id' => $this->customerId,
                'duration_ms' => $durationMs,
                'threshold_ms' => $threshold,
            ]);
        }
    }

    /**
     * Screen the customer against sanctions lists and enforce block-level
     * results. This restores the enforcement that previously lived inline in
     * TriggerSanctionsRescreening::handleCustomerUpdate(): a block-level match
     * places pending transactions under compliance review, raises a CRITICAL
     * alert for the compliance team, and writes the sanction audit entry.
     */
    protected function screenAndEnforce(CustomerScreeningService $service, AuditService $auditService, Customer $customer): void
    {
        $result = $service->screenCustomer($customer);
        $firstMatch = $result->matches->first();

        if (! $result->isBlocked()) {
            return;
        }

        // Place all pending transactions on hold
        $this->placePendingTransactionsForComplianceReview(
            $customer,
            "New sanctions match detected: {$firstMatch?->entityName}"
        );

        // Alert compliance team
        $this->createComplianceAlert(
            $customer,
            "CRITICAL: New sanctions match detected for customer {$customer->full_name} during rescreening. Matched entity: {$firstMatch?->entityName} (similarity: {$firstMatch?->matchScore}%)",
            AlertPriority::Critical
        );

        Log::critical('Sanctions match detected during queued compliance screening', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'matched_entity' => $firstMatch?->entityName,
            'similarity' => $firstMatch?->matchScore,
        ]);

        // Audit log the sanction event
        $auditService->logSanctionEvent('customer_record_updated_rescreening', $customer->id, [
            'entity_type' => 'Customer',
            'triggered_by' => 'ComplianceScreeningJob',
            'screening_result' => 'blocked',
            'matched_entity' => $firstMatch?->entityName,
        ]);
    }

    protected function placePendingTransactionsForComplianceReview(Customer $customer, string $reason): void
    {
        // Only PendingApproval and Approved work may be rewound to
        // PendingApproval. A Processing transaction can already have
        // partially-booked side effects (stock movements, journal entries);
        // knocking it back would let it re-run its approval flow and double
        // book, so it is left untouched for manual compliance handling.
        $holdableStatuses = [
            TransactionStatus::PendingApproval,
            TransactionStatus::Approved,
        ];

        $transactions = Transaction::where('customer_id', $customer->id)
            ->whereIn('status', $holdableStatuses)
            ->get();

        DB::transaction(function () use ($transactions, $customer, $reason) {
            foreach ($transactions as $transaction) {
                $transaction->status = TransactionStatus::PendingApproval;
                $transaction->save();

                $this->upsertTransactionSanctionFlag($customer, $transaction, $reason);
            }
        });

        $skippedProcessing = Transaction::where('customer_id', $customer->id)
            ->where('status', TransactionStatus::Processing)
            ->count();

        if ($skippedProcessing > 0) {
            Log::warning('Processing transactions skipped during sanctions hold to avoid double booking', [
                'customer_id' => $customer->id,
                'skipped_count' => $skippedProcessing,
                'reason' => $reason,
            ]);
        }

        if ($transactions->isNotEmpty()) {
            Log::warning('Pending transactions placed for compliance review due to sanctions match', [
                'customer_id' => $customer->id,
                'transaction_count' => $transactions->count(),
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Create or refresh the open SanctionMatch flag for a transaction so
     * repeated blocked rescreens accumulate evidence on one row instead of
     * stacking duplicate CRITICAL flags.
     */
    protected function upsertTransactionSanctionFlag(Customer $customer, Transaction $transaction, string $reason): FlaggedTransaction
    {
        // SoftDeletes keeps soft-deleted rows out of the lookup.
        $flag = FlaggedTransaction::where('customer_id', $customer->id)
            ->where('transaction_id', $transaction->id)
            ->where('flag_type', ComplianceFlagType::SanctionMatch)
            ->where('status', FlagStatus::Open)
            ->first();

        if ($flag === null) {
            return FlaggedTransaction::create([
                'customer_id' => $customer->id,
                'transaction_id' => $transaction->id,
                'flag_type' => ComplianceFlagType::SanctionMatch,
                'flag_reason' => $reason,
                'status' => FlagStatus::Open,
                'severity' => 'critical',
            ]);
        }

        $flag->flag_reason = $reason;
        $flag->notes = $this->appendDetectionNote($flag->notes, $reason);
        // save() bumps updated_at: the row's last-detected marker.
        $flag->save();

        return $flag;
    }

    protected function createComplianceAlert(Customer $customer, string $reason, AlertPriority $priority): void
    {
        $alert = Alert::where('customer_id', $customer->id)
            ->where('type', ComplianceFlagType::SanctionMatch)
            ->where('source', 'sanctions_rescreening')
            ->where('status', FlagStatus::Open)
            ->first();

        if ($alert === null) {
            Alert::create([
                'customer_id' => $customer->id,
                'type' => ComplianceFlagType::SanctionMatch,
                'priority' => $priority,
                'status' => FlagStatus::Open,
                'reason' => $reason,
                'source' => 'sanctions_rescreening',
            ]);
        } else {
            // Re-detection refreshes the open alert instead of stacking a
            // duplicate CRITICAL row; save() bumps updated_at as the
            // last-detected marker.
            $alert->priority = $priority;
            $alert->reason = $reason;
            $alert->save();
        }

        $flag = FlaggedTransaction::where('customer_id', $customer->id)
            ->whereNull('transaction_id')
            ->where('flag_type', ComplianceFlagType::SanctionMatch)
            ->where('status', FlagStatus::Open)
            ->first();

        if ($flag === null) {
            FlaggedTransaction::create([
                'customer_id' => $customer->id,
                'flag_type' => ComplianceFlagType::SanctionMatch,
                'flag_reason' => $reason,
                'status' => FlagStatus::Open,
                'severity' => $priority === AlertPriority::Critical ? 'critical' : 'high',
            ]);
        } else {
            $flag->flag_reason = $reason;
            $flag->notes = $this->appendDetectionNote($flag->notes, $reason);
            $flag->save();
        }
    }

    /**
     * Record a re-detection line on the flag's notes (BaseMonitor-style
     * evidence merge for models without a details column) so repeat hits
     * leave an audit trail on one row instead of creating duplicates.
     */
    protected function appendDetectionNote(?string $notes, string $reason): string
    {
        $entry = sprintf('[%s] %s', now()->toDateTimeString(), $reason);

        return trim(($notes !== null && $notes !== '' ? $notes."\n" : '').$entry);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('Compliance screening job failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['compliance', 'screening'];
    }
}
