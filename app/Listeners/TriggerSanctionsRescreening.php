<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Events\CustomerRecordUpdated;
use App\Events\SanctionsListUpdated;
use App\Jobs\ComplianceScreeningJob;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TriggerSanctionsRescreening
{
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
                    TransactionStatus::PendingApproval->value,
                    TransactionStatus::Approved->value,
                    TransactionStatus::Processing->value,
                ]);
            })
                ->orWhere(function ($recentQuery) {
                    $recentQuery->whereNotNull('last_transaction_at')
                        ->where('last_transaction_at', '>=', now()->subDays(30));
                });
        })
            ->orWhere('risk_rating', RiskRating::High->value)
            ->get();
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

        DB::transaction(function () use ($transactions, $customer, $reason, $pendingStatuses) {
            foreach ($transactions as $transaction) {
                // Lock each row and re-check the holdable set: a transaction
                // that transitioned since the unlocked read must not be
                // resurrected into PendingApproval.
                $locked = Transaction::whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($locked->status, $pendingStatuses, true)) {
                    continue;
                }

                $locked->status = TransactionStatus::PendingApproval;
                $locked->save();

                FlaggedTransaction::create([
                    'customer_id' => $customer->id,
                    'transaction_id' => $locked->id,
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
            'status' => AlertStatus::Open,
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
