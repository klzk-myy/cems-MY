<?php

namespace App\Listeners;

use App\Enums\RecalculationTrigger;
use App\Events\TransactionCreated;
use App\Models\Customer;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\System\CacheInvalidationService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransactionCreatedListener implements ShouldQueue
{
    // Ensure listener runs only after the outer DB transaction has committed
    public $afterCommit = true;

    protected TransactionMonitoringService $monitoringService;

    protected RiskScoringEngine $riskScoringService;

    protected CacheInvalidationService $cacheInvalidationService;

    public function __construct(
        TransactionMonitoringService $monitoringService,
        RiskScoringEngine $riskScoringService,
        CacheInvalidationService $cacheInvalidationService
    ) {
        $this->monitoringService = $monitoringService;
        $this->riskScoringService = $riskScoringService;
        $this->cacheInvalidationService = $cacheInvalidationService;
    }

    public function handle(TransactionCreated $event)
    {
        if ($event->transaction->exists) {
            // Monotonic write: queued events can be processed out of order,
            // so a delayed event must not overwrite a newer timestamp.
            Customer::whereKey($event->transaction->customer_id)
                ->where(fn ($q) => $q->whereNull('last_transaction_at')
                    ->orWhere('last_transaction_at', '<', $event->transaction->created_at))
                ->update(['last_transaction_at' => $event->transaction->created_at]);
        }

        // Invalidate cached report datasets — they aggregate over the
        // transaction and position tables that this write changed.
        $this->cacheInvalidationService->forgetReportData();

        $this->monitoringService->monitorTransaction($event->transaction);
        // recalculate() (not calculateScore(), which discards the result):
        // persists the CustomerRiskProfile, writes risk_score/risk_rating back
        // to the customer, records CustomerRiskHistory, and opens a
        // ComplianceFinding on score deltas >= 10.
        $this->riskScoringService->recalculate(
            $event->transaction->customer_id,
            RecalculationTrigger::EventDriven
        );
    }
}
