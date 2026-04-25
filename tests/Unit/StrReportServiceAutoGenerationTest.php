<?php

namespace Tests\Unit;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\StrStatus;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\StrReport;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\NarrativeGenerator;
use App\Services\StrReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrReportServiceAutoGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected StrReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StrReportService(
            app(ComplianceService::class),
            app(AuditService::class),
            app(NarrativeGenerator::class)
        );
    }

    public function test_evaluate_returns_null_for_non_triggering_alert(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::LargeAmount,
            'priority' => AlertPriority::Medium,
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNull($result);
    }

    public function test_evaluate_returns_draft_for_structuring_alert(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::Structuring,
            'priority' => AlertPriority::High,
            'reason' => 'Multiple sub-RM3k transactions detected',
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNotNull($result);
        $this->assertInstanceOf(StrReport::class, $result);
        $this->assertEquals($customer->id, $result->customer_id);
    }

    public function test_auto_generated_str_has_pending_approval_status(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::SanctionMatch,
            'priority' => AlertPriority::Critical,
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNotNull($result);
        $this->assertEquals(StrStatus::PendingApproval, $result->status);
    }

    public function test_evaluate_returns_null_when_str_already_exists(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::Structuring,
            'priority' => AlertPriority::High,
        ]);

        StrReport::factory()->create([
            'customer_id' => $customer->id,
            'status' => StrStatus::PendingApproval,
            'created_at' => now(),
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNull($result);
    }

    public function test_sanction_match_triggers_str(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::SanctionMatch,
            'priority' => AlertPriority::Critical,
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNotNull($result);
        $this->assertEquals(StrStatus::PendingApproval, $result->status);
    }

    public function test_risk_escalation_triggers_str(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'type' => ComplianceFlagType::RiskScoreEscalation,
            'priority' => AlertPriority::Medium,
        ]);

        $result = $this->service->evaluateAutoStrTriggers($alert);

        $this->assertNotNull($result);
        $this->assertEquals(StrStatus::PendingApproval, $result->status);
    }

    public function test_is_trigger_enabled_returns_correct_value(): void
    {
        $this->assertTrue($this->service->isTriggerEnabled('structuring'));
        $this->assertTrue($this->service->isTriggerEnabled('smurfing'));
        $this->assertTrue($this->service->isTriggerEnabled('sanction_match'));
        $this->assertTrue($this->service->isTriggerEnabled('risk_escalation'));
    }

    public function test_get_auto_str_trigger_config_returns_config(): void
    {
        $config = $this->service->getAutoStrTriggerConfig('structuring');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('min_transactions', $config);
        $this->assertTrue($config['enabled']);
    }
}
