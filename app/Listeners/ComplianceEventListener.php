<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\RiskRating;
use App\Enums\UserRole;
use App\Events\AlertCreated;
use App\Events\CaseOpened;
use App\Events\RiskScoreCalculated;
use App\Events\RiskScoreUpdated;
use App\Models\Alert;
use App\Models\FlaggedTransaction;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use App\Notifications\TransactionFlaggedNotification;
use App\Services\AuditService;
use App\Services\Compliance\CustomerRiskScoringService;
use App\Services\Compliance\EddTemplateService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ComplianceEventListener
{
    public function __construct(
        protected CustomerRiskScoringService $riskScoringService,
        protected EddTemplateService $eddTemplateService,
        protected AuditService $auditService
    ) {}

    public function handleAlertCreated(AlertCreated $event): void
    {
        $alert = $event->alert;

        $notifiableUsers = User::whereIn('role', [
            UserRole::ComplianceOfficer->value,
            UserRole::Admin->value,
        ])->get();

        if ($notifiableUsers->isEmpty()) {
            Log::warning('No compliance officers or admins found for alert notification', [
                'alert_id' => $alert->id,
            ]);

            return;
        }

        try {
            $flaggedTransaction = $alert->flaggedTransaction;
            if ($flaggedTransaction instanceof FlaggedTransaction) {
                $flaggedBy = $alert->assignedTo;

                Notification::send(
                    $notifiableUsers,
                    new TransactionFlaggedNotification($flaggedTransaction, $flaggedBy instanceof User ? $flaggedBy : null)
                );
            }

            Log::info('Alert created notification sent', [
                'alert_id' => $alert->id,
                'notification_count' => $notifiableUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send alert created notification', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function handleCaseOpened(CaseOpened $event): void
    {
        $case = $event->case;

        // When a case is opened, recalculate customer risk score
        $this->riskScoringService->calculateAndSnapshot($case->customer_id);

        // Check if EDD should be prompted based on case priority. Compare the
        // enum directly: the old lowercase string comparison only worked while
        // alert-derived cases stored raw AlertPriority (lowercase) values in
        // the case priority column.
        if ($case->priority && in_array($case->priority, [ComplianceCasePriority::Critical, ComplianceCasePriority::High], true)) {
            $this->eddTemplateService->getRecommendedTemplate([
                'transaction_amount' => $case->alerts->first()?->risk_score * 1000,
                'high_risk_country' => $case->alerts->contains(fn ($a) => $a->type === ComplianceFlagType::HighRiskCountry),
            ]);
        }
    }

    public function handleRiskScoreCalculated(RiskScoreCalculated $event): void
    {
        Log::info('Risk score calculated', [
            'customer_id' => $event->customer->id,
        ]);
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
                    'rating' => $snapshot->previous_rating instanceof RiskRating
                        ? $snapshot->previous_rating->value
                        : $snapshot->previous_rating,
                ],
                'new_values' => [
                    'score' => $snapshot->overall_score,
                    'rating' => $snapshot->overall_rating_label,
                ],
            ],
            'INFO'
        );

        // Alert compliance officer if score crossed HIGH/CRITICAL threshold.
        // Compare against the actual enum/score values: previous_rating is a
        // RiskRating enum (Low/Medium/High) and high risk is score >= 60. The
        // old lowercase 'high_risk'/'critical_risk' string comparisons never
        // matched, so escalation alerts were dead code.
        $oldWasHighRisk = $snapshot->previous_rating instanceof RiskRating
            && $snapshot->previous_rating->isHigh();
        $newIsHighRisk = $snapshot->isHighRisk(); // overall_score >= 60

        if (! $oldWasHighRisk && $newIsHighRisk) {
            $this->alertOnRiskEscalation($snapshot);
        }
    }

    protected function alertOnRiskEscalation(RiskScoreSnapshot $snapshot): void
    {
        // Dedup: rescreening fires RiskScoreUpdated for every high-score
        // snapshot; only surface one open escalation alert per customer.
        $existing = Alert::where('customer_id', $snapshot->customer_id)
            ->where('type', ComplianceFlagType::RiskScoreEscalation)
            ->where('status', FlagStatus::Open)
            ->exists();

        if ($existing) {
            return;
        }

        $priority = $snapshot->isCritical()
            ? AlertPriority::Critical
            : AlertPriority::High;
        $level = $snapshot->isCritical() ? 'Critical' : 'High';

        Alert::create([
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation,
            'status' => FlagStatus::Open,
            'priority' => $priority,
            'risk_score' => $snapshot->overall_score,
            'reason' => "Customer risk score escalated to {$level} (score: {$snapshot->overall_score})",
        ]);
    }

    public function subscribe($events): array
    {
        return [
            AlertCreated::class => 'handleAlertCreated',
            CaseOpened::class => 'handleCaseOpened',
            RiskScoreCalculated::class => 'handleRiskScoreCalculated',
            RiskScoreUpdated::class => 'handleRiskScoreUpdated',
        ];
    }
}
