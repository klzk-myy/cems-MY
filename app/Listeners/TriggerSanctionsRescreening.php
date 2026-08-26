<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Events\CustomerRecordUpdated;
use App\Events\SanctionsListUpdated;
use App\Jobs\ComplianceScreeningJob;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\CustomerScreeningService;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TriggerSanctionsRescreening
{
    public function __construct(
        protected CustomerScreeningService $sanctionScreeningService,
        protected AuditService $auditService
    ) {}

    public function handleCustomerUpdate(CustomerRecordUpdated $event): void
    {
        ComplianceScreeningJob::dispatch($event->customer->id)->onQueue('compliance');
    }

    public function handleSanctionsUpdate(SanctionsListUpdated $event): void
    {
        Log::info('Sanctions list updated - initiating batch rescreening', [
            'source' => $event->source,
            'previous_version' => $event->previousVersion,
            'new_version' => $event->newVersion,
            'new_entries' => $event->newEntriesCount,
            'removed_entries' => $event->removedEntriesCount,
        ]);

        $customersToRescreen = $this->getCustomersToRescreen();

        Log::info('Sanctions batch rescreening initiated', [
            'customers_to_rescreen' => $customersToRescreen->count(),
            'source' => $event->source,
        ]);

        if ($customersToRescreen->isEmpty()) {
            return;
        }

        Bus::batch(
            $customersToRescreen->map(fn (Customer $c) => new ComplianceScreeningJob($c->id))->toArray()
        )->then(fn (Batch $batch) => Log::info("Screened {$batch->totalJobs} customers"))
            ->catch(fn (Batch $batch, \Throwable $e) => Log::error('Screening batch failed', [$e->getMessage()]))
            ->onQueue('compliance')
            ->onConnection('redis')
            ->dispatch();
    }

    protected function getCustomersToRescreen(): Collection
    {
        return Customer::where(function ($query) {
            $query->whereHas('transactions', function ($txQuery) {
                $txQuery->whereIn('status', [
                    TransactionStatus::PendingApproval,
                    TransactionStatus::Approved,
                    TransactionStatus::Processing,
                ]);
            })
                ->orWhere(function ($recentQuery) {
                    $recentQuery->whereNotNull('last_transaction_at')
                        ->where('last_transaction_at', '>=', now()->subDays(30));
                });
        })
            ->orWhere('risk_rating', 'High')
            ->get();
    }

    protected function rescreenCustomerWithTransactionHold(Customer $customer, SanctionsListUpdated $event): void
    {
        $previousSanctionHit = $customer->sanction_hit;

        $result = $this->sanctionScreeningService->screenCustomer($customer);
        $firstMatch = $result->matches->first();

        $isNewMatch = $result->isBlocked() && ! $previousSanctionHit;

        if ($isNewMatch) {
            $this->placePendingTransactionsForComplianceReview($customer, "New sanctions match detected after list update: {$firstMatch?->entityName}");

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

        $this->auditService->logSanctionEvent('batch_sanctions_rescreening', $customer->id, [
            'entity_type' => 'Customer',
            'triggered_by' => 'SanctionsListUpdated',
            'list_source' => $event->source,
            'list_version' => $event->newVersion,
            'screening_result' => $result->isBlocked() ? 'blocked' : 'passed',
            'is_new_match' => $isNewMatch,
        ]);
    }

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

        DB::transaction(function () use ($transactions, $customer, $reason) {
            foreach ($transactions as $transaction) {
                $transaction->status = TransactionStatus::PendingApproval;
                $transaction->save();

                FlaggedTransaction::create([
                    'customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,
                    'flag_type' => ComplianceFlagType::SanctionMatch,
                    'flag_reason' => $reason,
                    'status' => FlagStatus::Open,
                    'severity' => 'critical',
                ]);
            }
        });

        if ($transactions->isNotEmpty()) {
            Log::warning('Pending transactions placed for compliance review due to sanctions match', [
                'customer_id' => $customer->id,
                'transaction_count' => $transactions->count(),
                'reason' => $reason,
            ]);
        }
    }

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

        FlaggedTransaction::create([
            'customer_id' => $customer->id,
            'flag_type' => ComplianceFlagType::SanctionMatch,
            'flag_reason' => $reason,
            'status' => FlagStatus::Open,
            'severity' => $priority === AlertPriority::Critical ? 'critical' : 'high',
        ]);
    }

    public function subscribe($events): array
    {
        return [
            CustomerRecordUpdated::class => 'handleCustomerUpdate',
            SanctionsListUpdated::class => 'handleSanctionsUpdate',
        ];
    }
}
