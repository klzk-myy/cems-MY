<?php

namespace Tests\Unit\Listeners;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\RiskRating;
use App\Events\RiskScoreUpdated;
use App\Listeners\ComplianceEventListener;
use App\Models\Alert;
use App\Models\RiskScoreSnapshot;
use App\Models\SystemLog;
use App\Services\AuditService;
use App\Services\Compliance\CustomerRiskScoringService;
use App\Services\Compliance\EddTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceEventListenerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Build the listener with mocked collaborators so only the risk-escalation
     * path (AuditService log + Alert creation) hits the database.
     */
    private function makeListener(): ComplianceEventListener
    {
        $riskScoring = Mockery::mock(CustomerRiskScoringService::class);
        $eddTemplates = Mockery::mock(EddTemplateService::class);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('logWithSeverity')->andReturn(Mockery::mock(SystemLog::class));

        return new ComplianceEventListener($riskScoring, $eddTemplates, $audit);
    }

    #[Test]
    public function escalation_alert_fires_when_risk_crosses_high_threshold(): void
    {
        // Regression: previous_rating is a RiskRating enum and high risk is
        // score >= 60. The old 'high_risk'/'critical_risk' string comparisons
        // never matched, so escalation alerts were dead code.
        $snapshot = RiskScoreSnapshot::factory()->create([
            'customer_id' => $this->createTestCustomer()->id,
            'previous_rating' => RiskRating::Low,
            'overall_score' => 70,
        ]);

        $this->makeListener()->handleRiskScoreUpdated(new RiskScoreUpdated($snapshot));

        $this->assertDatabaseHas('alerts', [
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation->value,
            'priority' => AlertPriority::High->value,
            'status' => 'open',
        ]);
    }

    #[Test]
    public function escalation_alert_is_critical_when_score_is_80_plus(): void
    {
        $snapshot = RiskScoreSnapshot::factory()->create([
            'customer_id' => $this->createTestCustomer()->id,
            'previous_rating' => RiskRating::Medium,
            'overall_score' => 88,
        ]);

        $this->makeListener()->handleRiskScoreUpdated(new RiskScoreUpdated($snapshot));

        $this->assertDatabaseHas('alerts', [
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation->value,
            'priority' => AlertPriority::Critical->value,
        ]);
    }

    #[Test]
    public function no_escalation_alert_when_previously_already_high_risk(): void
    {
        $snapshot = RiskScoreSnapshot::factory()->create([
            'customer_id' => $this->createTestCustomer()->id,
            'previous_rating' => RiskRating::High,
            'overall_score' => 75,
        ]);

        $this->makeListener()->handleRiskScoreUpdated(new RiskScoreUpdated($snapshot));

        $this->assertDatabaseMissing('alerts', [
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation->value,
        ]);
    }

    #[Test]
    public function no_escalation_alert_when_still_below_high_threshold(): void
    {
        $snapshot = RiskScoreSnapshot::factory()->create([
            'customer_id' => $this->createTestCustomer()->id,
            'previous_rating' => RiskRating::Low,
            'overall_score' => 25,
        ]);

        $this->makeListener()->handleRiskScoreUpdated(new RiskScoreUpdated($snapshot));

        $this->assertDatabaseMissing('alerts', [
            'customer_id' => $snapshot->customer_id,
            'type' => ComplianceFlagType::RiskScoreEscalation->value,
        ]);
    }

    #[Test]
    public function repeated_high_risk_snapshots_do_not_duplicate_open_escalation_alert(): void
    {
        // Regression: rescreening re-snapshots high-risk customers on a
        // schedule, so without dedup every snapshot would create another
        // open escalation alert.
        $customer = $this->createTestCustomer();
        Alert::create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::RiskScoreEscalation,
            'status' => FlagStatus::Open,
            'priority' => AlertPriority::High,
            'risk_score' => 70,
            'reason' => 'existing escalation',
        ]);

        $snapshot = RiskScoreSnapshot::factory()->create([
            'customer_id' => $customer->id,
            'previous_rating' => RiskRating::Low,
            'overall_score' => 72,
        ]);

        $this->makeListener()->handleRiskScoreUpdated(new RiskScoreUpdated($snapshot));

        $this->assertSame(1, Alert::where('customer_id', $customer->id)
            ->where('type', ComplianceFlagType::RiskScoreEscalation->value)
            ->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
