<?php

namespace App\Listeners;

use App\Enums\RecalculationTrigger;
use App\Events\TransactionCreated;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransactionCreatedListener implements ShouldQueue
{
    // Ensure listener runs only after the outer DB transaction has committed
    public $afterCommit = true;

    protected TransactionMonitoringService $monitoringService;

    protected RiskScoringEngine $riskScoringService;

    public function __construct(
        TransactionMonitoringService $monitoringService,
        RiskScoringEngine $riskScoringService
    ) {
        $this->monitoringService = $monitoringService;
        $this->riskScoringService = $riskScoringService;
    }

    public function handle(TransactionCreated $event)
    {
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
