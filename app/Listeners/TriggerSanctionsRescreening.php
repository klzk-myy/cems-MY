<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Events\CustomerRecordUpdated;
use App\Events\SanctionsListUpdated;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Support\Facades\Log;

class TriggerSanctionsRescreening
{
    public function __construct(
        protected UnifiedSanctionScreeningService $sanctionScreeningService,
        protected AuditService $auditService
    ) {}

    /**
     * Handle CustomerRecordUpdated event.
     * Immediately rescreen the updated customer against sanctions lists.
     */
    public function handleCustomerUpdate(CustomerRecordUpdated $event): void
    {
        $customer = $event->customer;

        // Rescreen the customer
        $result = $this->sanctionScreeningService->screenCustomer($customer);
        $firstMatch = $result->matches->first();

        if ($result->isBlocked()) {
            // New sanctions match found - place all pending transactions on hold
            $this->placePendingTransactionsForComplianceReview($customer, "New sanctions match detected: {$firstMatch?->entityName}");

            // Alert compliance team
            $this->createComplianceAlert(
                $customer,
                "CRITICAL: New sanctions match detected for customer {$customer->full_name} during record update. Matched entity: {$firstMatch?->entityName} (similarity: {$firstMatch?->matchScore}%)",
                AlertPriority::Critical
            );

            Log::critical('Sanctions rescreening triggered by customer update', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'matched_entity' => $firstMatch?->entityName,
                'similarity' => $firstMatch?->matchScore,
                'changed_fields' => $event->changedFields,
                'updated_by' => $event->updatedBy,
            ]);
        } else {
            // Customer passed sanctions screening
            Log::info('Customer passed sanctions rescreening', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
            ]);
        }

        // Audit log the rescreening
        $this->auditService->logSanctionEvent('customer_record_updated_rescreening', $customer->id, [
            'entity_type' => 'Customer',
            'triggered_by' => 'CustomerRecordUpdated',
            'changed_fields' => $event->changedFields,
            'screening_result' => $result->isBlocked() ? 'blocked' : 'passed',
            'matched_entity' => $firstMatch?->entityName,
        ]);
    }

    /**
     * Handle SanctionsListUpdated event.
     * Rescreen affected customers when sanctions lists are refreshed.
     */
    public function handleSanctionsUpdate(SanctionsListUpdated $event): void
    {
        Log::info('Sanctions list updated - initiating batch rescreening', [
            'source' => $event->source,
            'previous_version' => $event->previousVersion,
            'new_version' => $event->newVersion,
            'new_entries' => $event->newEntriesCount,
            'removed_entries' => $event->removedEntriesCount,
        ]);

        // Get customers to rescreen:
        // 1. Customers with pending/active transactions in last 30 days
        // 2. High risk rated customers
        $customersToRescreen = $this->getCustomersToRescreen();

        Log::info('Sanctions batch rescreening initiated', [
            'customers_to_rescreen' => $customersToRescreen->count(),
            'source' => $event->source,
        ]);

        foreach ($customersToRescreen as $customer) {
            $this->rescreenCustomerWithTransactionHold($customer, $event);
        }
    }

    /**
     * Get customers that need rescreening based on activity and risk profile.
     */
    protected function getCustomersToRescreen(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::where(function ($query) {
            // Customers with pending transactions
            $query->whereHas('transactions', function ($txQuery) {
                $txQuery->whereIn('status', [
                    TransactionStatus::PendingApproval,
                    TransactionStatus::Approved,
                    TransactionStatus::Processing,
                ]);
            })
            // OR customers with recent activity in last 30 days
                ->orWhereNotNull('last_transaction_at')
                ->where('last_transaction_at', '>=', now()->subDays(30));
        })
            ->orWhere('risk_rating', 'High')
            ->with('transactions')
            ->get();
    }

    /**
     * Rescreen a customer and place transactions on hold if new match found.
     */
    protected function rescreenCustomerWithTransactionHold(Customer $customer, SanctionsListUpdated $event): void
    {
        $previousSanctionHit = $customer->sanction_hit;

        $result = $this->sanctionScreeningService->screenCustomer($customer);
        $firstMatch = $result->matches->first();

        // Check if this is a NEW sanctions match (was not flagged before)
        $isNewMatch = $result->isBlocked() && ! $previousSanctionHit;

        if ($isNewMatch) {
            // New match found - place all pending transactions on hold
            $this->placePendingTransactionsForComplianceReview($customer, "New sanctions match detected after list update: {$firstMatch?->entityName}");

            // Create compliance alert
            $this->createComplianceAlert(
                $customer,
                "CRITICAL: New sanctions match detected after {$event->source} list update (v{$event->previousVersion} -> v{$event->newVersion}). Customer: {$customer->full_name}. Matched: {$firstMatch?->entityName} ({$firstMatch?->matchScore}% similar).",
                AlertPriority::Critical
            );

            Log::critical('New sanctions match detected during batch rescreening', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'matched_entity' => $firstMatch?->entityName,
                'list_source' => $event->source,
                'list_version_change' => "{$event->previousVersion} -> {$event->newVersion}",
            ]);
        }

        // Audit log
        $this->auditService->logSanctionEvent('batch_sanctions_rescreening', $customer->id, [
            'entity_type' => 'Customer',
            'triggered_by' => 'SanctionsListUpdated',
            'list_source' => $event->source,
            'list_version' => $event->newVersion,
            'screening_result' => $result->isBlocked() ? 'blocked' : 'passed',
            'is_new_match' => $isNewMatch,
        ]);
    }

    /**
     * Place all pending transactions for a customer under compliance review.
     */
    protected function placePendingTransactionsForComplianceReview(Customer $customer, string $reason): void
    {
        $pendingStatuses = [
            TransactionStatus::PendingApproval,
            TransactionStatus::Approved,
            TransactionStatus::Processing,
        ];

        $transactions = Transaction::where('customer_id', $customer->id)
            ->whereIn('status', $pendingStatuses)
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->update(['status' => TransactionStatus::PendingApproval]);

            FlaggedTransaction::create([
                'customer_id' => $customer->id,
                'transaction_id' => $transaction->id,
                'flag_type' => ComplianceFlagType::SanctionMatch,
                'flag_reason' => $reason,
                'status' => FlagStatus::Open,
                'severity' => 'critical',
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
     * Create a compliance alert for the compliance team.
     */
    protected function createComplianceAlert(Customer $customer, string $reason, AlertPriority $priority): void
    {
        Alert::create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::SanctionMatch,
            'priority' => $priority,
            'status' => FlagStatus::Open,
            'reason' => $reason,
            'source' => 'sanctions_rescreening',
        ]);

        // Also create a FlaggedTransaction record for audit trail
        FlaggedTransaction::create([
            'customer_id' => $customer->id,
            'flag_type' => ComplianceFlagType::SanctionMatch,
            'flag_reason' => $reason,
            'status' => FlagStatus::Open,
            'severity' => $priority === AlertPriority::Critical ? 'critical' : 'high',
        ]);
    }

    /**
     * Register the events this listener subscribes to.
     */
    public function subscribe($events): array
    {
        return [
            CustomerRecordUpdated::class => 'handleCustomerUpdate',
            SanctionsListUpdated::class => 'handleSanctionsUpdate',
        ];
    }
}
