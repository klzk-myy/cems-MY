<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ThresholdAudit;
use App\Models\User;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThresholdServiceTest extends TestCase
{
    use RefreshDatabase;

    private ThresholdService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ThresholdService;
    }

    #[Test]
    public function get_auto_approve_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getAutoApproveThreshold());
    }

    #[Test]
    public function get_manager_approval_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getManagerApprovalThreshold());
    }

    #[Test]
    public function get_standard_cdd_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getStandardCddThreshold());
    }

    #[Test]
    public function get_large_transaction_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getLargeTransactionThreshold());
    }

    #[Test]
    public function get_str_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getStrThreshold());
    }

    #[Test]
    public function get_edd_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getEddThreshold());
    }

    #[Test]
    public function get_risk_high_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getRiskHighThreshold());
    }

    #[Test]
    public function get_risk_medium_threshold(): void
    {
        $this->assertEquals('30000', $this->service->getRiskMediumThreshold());
    }

    #[Test]
    public function get_risk_low_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getRiskLowThreshold());
    }

    #[Test]
    public function get_alert_critical_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getAlertCriticalThreshold());
    }

    #[Test]
    public function get_alert_high_threshold(): void
    {
        $this->assertEquals('30000', $this->service->getAlertHighThreshold());
    }

    #[Test]
    public function get_alert_medium_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getAlertMediumThreshold());
    }

    #[Test]
    public function get_variance_yellow_threshold(): void
    {
        $this->assertEquals('100.00', $this->service->getVarianceYellowThreshold());
    }

    #[Test]
    public function get_variance_red_threshold(): void
    {
        $this->assertEquals('500.00', $this->service->getVarianceRedThreshold());
    }

    #[Test]
    public function get_structuring_sub_threshold(): void
    {
        $this->assertEquals('3000', $this->service->getStructuringSubThreshold());
    }

    #[Test]
    public function get_structuring_min_transactions(): void
    {
        $this->assertEquals(3, $this->service->getStructuringMinTransactions());
    }

    #[Test]
    public function get_duration_warning_hours(): void
    {
        $this->assertEquals(24, $this->service->getDurationWarningHours());
    }

    #[Test]
    public function get_duration_critical_hours(): void
    {
        $this->assertEquals(48, $this->service->getDurationCriticalHours());
    }

    #[Test]
    public function get_velocity_alert_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getVelocityAlertThreshold());
    }

    #[Test]
    public function all_amount_thresholds_return_string(): void
    {
        $amountMethods = [
            'getAutoApproveThreshold',
            'getManagerApprovalThreshold',
            'getStandardCddThreshold',
            'getLargeTransactionThreshold',
            'getStrThreshold',
            'getEddThreshold',
            'getRiskHighThreshold',
            'getRiskMediumThreshold',
            'getRiskLowThreshold',
            'getAlertCriticalThreshold',
            'getAlertHighThreshold',
            'getAlertMediumThreshold',
            'getVarianceYellowThreshold',
            'getVarianceRedThreshold',
            'getStructuringSubThreshold',
            'getVelocityAlertThreshold',
        ];

        foreach ($amountMethods as $method) {
            $value = $this->service->$method();
            $this->assertIsString($value, "{$method} should return string");
        }
    }

    #[Test]
    public function all_count_thresholds_return_int(): void
    {
        $countMethods = [
            'getStructuringMinTransactions',
            'getStructuringHourlyWindow',
            'getStructuringLookupDays',
            'getDurationWarningHours',
            'getDurationCriticalHours',
            'getVelocityWindowDays',
        ];

        foreach ($countMethods as $method) {
            $value = $this->service->$method();
            $this->assertIsInt($value, "{$method} should return int");
        }
    }

    #[Test]
    public function set_audits_threshold_change(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        $result = $this->service->set('approval', 'auto_approve', '15000', 'Testing audit');

        $this->assertTrue($result);

        $audit = ThresholdAudit::where('category', 'approval')
            ->where('key', 'auto_approve')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('10000', $audit->old_value);
        $this->assertEquals('15000', $audit->new_value);
        $this->assertEquals('Testing audit', $audit->change_reason);
        $this->assertEquals($admin->id, $audit->changed_by);
    }

    #[Test]
    public function set_does_not_audit_when_value_unchanged(): void
    {
        $this->actingAs($this->adminUser());

        // Set same value as current config
        $result = $this->service->set('approval', 'auto_approve', '10000', 'Should not audit');

        $this->assertFalse($result);

        $auditCount = ThresholdAudit::where('category', 'approval')
            ->where('key', 'auto_approve')
            ->where('change_reason', 'Should not audit')
            ->count();

        $this->assertEquals(0, $auditCount);
    }

    #[Test]
    public function set_updates_config_value(): void
    {
        $this->actingAs($this->adminUser());

        $this->service->set('approval', 'auto_approve', '20000');

        $this->assertEquals('20000', config('thresholds.approval.auto_approve'));
    }

    private function adminUser()
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
