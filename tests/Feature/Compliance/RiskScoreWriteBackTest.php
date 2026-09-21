<?php

namespace Tests\Feature\Compliance;

use App\Enums\RiskRating;
use App\Models\Compliance\CustomerRiskHistory;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Compliance\CustomerRiskScoringService;
use App\Services\Compliance\PepAssessmentService;
use App\Services\Compliance\RiskCalculationService;
use App\Services\Compliance\RiskScoreWriteBackService;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\Compliance\RoundTripDetector;
use App\Services\Risk\AmountRiskService;
use App\Services\Risk\GeographicRiskService;
use App\Services\Risk\PatternRiskService;
use App\Services\Risk\StructuringRiskService;
use App\Services\Risk\VelocityRiskService;
use App\Services\Screening\CustomerScreeningService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\ValueObjects\ScreeningResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskScoreWriteBackTest extends TestCase
{
    use RefreshDatabase;

    private RiskScoringEngine $engine;

    private CustomerRiskScoringService $scoringService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RiskScoringEngine(
            new MathService,
            app(RiskCalculationService::class),
            new RiskScoreWriteBackService,
        );

        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $riskCalculationService = new RiskCalculationService(
            $mathService,
            $thresholdService,
            new VelocityRiskService($mathService, $thresholdService),
            new StructuringRiskService($mathService, $thresholdService),
            new GeographicRiskService($thresholdService),
            new AmountRiskService($mathService, $thresholdService),
            new PatternRiskService($mathService, new RoundTripDetector($mathService)),
        );

        $screeningService = $this->createMock(CustomerScreeningService::class);
        $screeningService->method('screenCustomer')->willReturn(new ScreeningResponse(
            action: 'clear',
            confidenceScore: 0.0,
            matches: new Collection,
            screenedAt: Carbon::now(),
        ));

        $this->scoringService = new CustomerRiskScoringService(
            $screeningService,
            new AuditService,
            $mathService,
            $riskCalculationService,
            new PepAssessmentService,
            new GeographicRiskService($thresholdService),
            new AmountRiskService($mathService, $thresholdService),
            new RiskScoreWriteBackService,
        );
    }

    #[Test]
    public function recalculate_for_customer_writes_score_back_to_customer_record(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_score' => 0,
            'risk_rating' => 'low',
        ]);

        $profile = $this->engine->recalculateForCustomer($customer->id);

        $customer->refresh();

        // Engine base score is 20, so the computed score always differs
        // from the seeded 0 and must be written back.
        $this->assertGreaterThanOrEqual(20, $customer->risk_score);
        $this->assertSame($profile->risk_score, $customer->risk_score);
        $riskRating = $customer->risk_rating;
        $this->assertInstanceOf(RiskRating::class, $riskRating);
        $this->assertSame($profile->risk_tier === RiskRating::Critical->value ? RiskRating::High->value : $profile->risk_tier, (string) $riskRating->value);
        $this->assertNotNull($customer->risk_assessed_at);
    }

    #[Test]
    public function initial_scoring_records_history_row_with_trigger(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'risk_score' => 0,
            'risk_rating' => 'low',
        ]);

        $this->engine->recalculateForCustomer($customer->id);

        $this->assertDatabaseHas('customer_risk_history', [
            'customer_id' => $customer->id,
            'old_score' => 0,
            'change_reason' => 'initial',
        ]);

        $history = CustomerRiskHistory::where('customer_id', $customer->id)->first();
        $this->assertNotNull($history);
        $this->assertSame($customer->fresh()->risk_score, $history->new_score);
        $this->assertEquals(RiskRating::Low->value, $history->old_rating->value);
    }

    #[Test]
    public function rescreen_customer_updates_customer_score_and_writes_history(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'risk_score' => 0,
            'risk_rating' => 'low',
        ]);

        // Give the customer enough recent activity to produce a non-zero
        // overall score so the write-back registers an actual change.
        Transaction::factory()->for($customer)->create([
            'amount_myr' => '15000',
            'created_at' => now(),
        ]);

        $result = $this->scoringService->rescreenCustomer($customer->id);

        $this->assertFalse($result['locked']);

        $customer->refresh();

        // Snapshot overall is the capped sum of transaction-driven sub-scores.
        $expectedOverall = min(
            $result['snapshot']->velocity_score
            + $result['snapshot']->structuring_score
            + $result['snapshot']->amount_score,
            100
        );

        $this->assertSame($expectedOverall, $customer->risk_score);
        $this->assertNotNull($customer->risk_assessed_at);
        $this->assertDatabaseHas('customer_risk_history', [
            'customer_id' => $customer->id,
            'old_score' => 0,
            'new_score' => $expectedOverall,
            'change_reason' => 'rescreen',
        ]);
    }

    #[Test]
    public function rescreen_customer_skips_side_effects_for_locked_profile(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'risk_score' => 42,
            'risk_rating' => 'medium',
        ]);
        $assessedAtBefore = $customer->risk_assessed_at?->toIso8601String();

        // Seed an existing profile and lock it (EDD review hold).
        $profile = CustomerRiskProfile::createForCustomer($customer->id, 42);
        $profile->lock(User::factory()->create()->id, 'EDD review in progress');

        $snapshotsBefore = $customer->riskScoreSnapshots()->count();

        $result = $this->scoringService->rescreenCustomer($customer->id);

        $this->assertTrue($result['locked']);
        $this->assertSame(42, $result['new_score']);
        $this->assertSame(0, $result['score_change']);

        // No snapshot created, customer record untouched, no audit noise.
        $this->assertSame($snapshotsBefore, $customer->riskScoreSnapshots()->count());
        $customer->refresh();
        $this->assertSame(42, $customer->risk_score);
        $this->assertSame($assessedAtBefore, $customer->risk_assessed_at?->toIso8601String());
        $this->assertDatabaseMissing('customer_risk_history', ['customer_id' => $customer->id]);
        $this->assertDatabaseMissing('system_logs', ['action' => 'customer_risk_score_changed']);
    }
}
