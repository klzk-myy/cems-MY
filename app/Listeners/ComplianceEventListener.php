<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Events\AlertCreated;
use App\Events\CaseOpened;
use App\Events\RiskScoreUpdated;
use App\Models\Alert;
use App\Models\RiskScoreSnapshot;
use App\Services\AuditService;
use App\Services\CustomerRiskScoringService;
use App\Services\EddTemplateService;

class ComplianceEventListener
{
    public function __construct(
        protected CustomerRiskScoringService $riskScoringService,
        protected EddTemplateService $eddTemplateService,
        protected AuditService $auditService
    ) {}

    public function handleAlertCreated(AlertCreated $event): void
    {
        // Alert created - could trigger notification to compliance officers
    }

    public function handleCaseOpened(CaseOpened $event): void
    {
        $case = $event->case;

        // When a case is opened, recalculate customer risk score
        $this->riskScoringService->calculateAndSnapshot($case->customer_id);

        // Check if EDD should be prompted based on case priority
        if ($case->priority && in_array($case->priority->value, ['critical', 'high'])) {
            $this->eddTemplateService->getRecommendedTemplate([
                'transaction_amount' => $case->alerts->first()?->risk_score * 1000,
                'high_risk_country' => $case->alerts->contains(fn ($a) => $a->type && $a->type->value === 'high_risk_country'),
            ]);
        }
    }

    public function handleRiskScoreUpdated(RiskScoreUpdated $event): void
    {
        $snapshot = $event->snapshot;

        // Log all score changes to audit trail
        $this->auditService->logWithSeverity(
            'risk_score_updated',
            [
                'entity_type' => 'Customer',
                'entity_id' => $snapshot->customer_id,
                'old_values' => [
                    'score' => $snapshot->previous_score,
                    'rating' => $snapshot->previous_rating,
                ],
                'new_values' => [
                    'score' => $snapshot->overall_score,
                    'rating' => $snapshot->overall_rating_label,
                ],
            ],
            'INFO'
        );

        // Alert compliance officer if score crossed HIGH/CRITICAL threshold
        $highRiskRatings = ['high_risk', 'critical_risk'];
        $oldWasHighRisk = in_array($snapshot->previous_rating, $highRiskRatings);
        $newIsHighRisk = in_array($snapshot->overall_rating_label, $highRiskRatings);

        if (! $oldWasHighRisk && $newIsHighRisk) {
            $this->alertOnRiskEscalation($snapshot);
        }
    }

    protected function alertOnRiskEscalation(RiskScoreSnapshot $snapshot): void
    {
        $priority = $snapshot->overall_rating_label === 'critical_risk'
            ? AlertPriority::Critical
            : AlertPriority::High;

        Alert::create([
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation,
            'status' => FlagStatus::Open,
            'priority' => $priority,
            'risk_score' => $snapshot->overall_score,
            'reason' => "Customer risk score escalated to {$snapshot->overall_rating_label} (score: {$snapshot->overall_score})",
        ]);
    }

    public function subscribe($events): array
    {
        return [
            AlertCreated::class => 'handleAlertCreated',
            CaseOpened::class => 'handleCaseOpened',
            RiskScoreUpdated::class => 'handleRiskScoreUpdated',
        ];
    }
}
