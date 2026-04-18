<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Services\TransactionMonitoringService;
use App\Services\UnifiedRiskScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransactionCreatedListener implements ShouldQueue
{
    // Ensure listener runs only after the outer DB transaction has committed
    public $afterCommit = true;

    protected TransactionMonitoringService $monitoringService;

    protected UnifiedRiskScoringService $riskScoringService;

    public function __construct(
        TransactionMonitoringService $monitoringService,
        UnifiedRiskScoringService $riskScoringService
    ) {
        $this->monitoringService = $monitoringService;
        $this->riskScoringService = $riskScoringService;
    }

    public function handle(TransactionCreated $event)
    {
        $this->monitoringService->monitorTransaction($event->transaction);
        $this->riskScoringService->calculateRiskScore($event->transaction->customer);
    }
}
