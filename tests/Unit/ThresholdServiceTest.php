<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Helpers\Thresholdable;
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
    public function get_velocity_amount_window_hours(): void
    {
        $this->assertEquals(24, $this->service->getVelocityAmountWindowHours());
    }

    #[Test]
    public function get_geographic_risk_weights(): void
    {
        $this->assertEquals(30, $this->service->getGeographicHighCountryWeight());
        $this->assertEquals(15, $this->service->getGeographicRecentTravelWeight());
    }

    #[Test]
    public function get_position_limit_returns_limit_for_known_currency(): void
    {
        $this->assertEquals('1000000', $this->service->getPositionLimit('USD'));
        $this->assertEquals('1000000', $this->service->getPositionLimit('usd'));
    }

    #[Test]
    public function get_position_limit_returns_null_for_unknown_currency(): void
    {
        $this->assertNull($this->service->getPositionLimit('XXX'));
    }

    #[Test]
    public function get_position_limits_returns_uppercase_keyed_map(): void
    {
        $limits = $this->service->getPositionLimits();

        $this->assertArrayHasKey('USD', $limits);
        $this->assertArrayHasKey('JPY', $limits);
        $this->assertEquals('1000000', $limits['USD']);
        $this->assertEquals('100000000', $limits['JPY']);
    }

    #[Test]
    public function set_persists_geographic_risk_and_position_limit_overrides(): void
    {
        $this->actingAs($this->adminUser());

        $this->service->set('geographic_risk', 'high_country_weight', 40);
        $this->service->set('position_limits', 'usd', '2000000', 'raise USD cap');

        $fresh = new ThresholdService;
        $this->assertEquals(40, $fresh->getGeographicHighCountryWeight());
        $this->assertEquals('2000000', $fresh->getPositionLimit('USD'));

        $audit = ThresholdAudit::where('category', 'position_limits')->where('key', 'usd')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('raise USD cap', $audit->change_reason);
    }

    #[Test]
    public function set_rejects_unknown_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->set('not_a_category', 'key', '1');
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

    #[Test]
    public function get_does_not_mutate_config_repository(): void
    {
        $this->actingAs($this->adminUser());
        $this->service->set('approval', 'auto_approve', '15000');

        // Simulate a fresh long-lived worker: config holds the file/env
        // default while the DB override is active.
        config(['thresholds.approval.auto_approve' => '10000']);

        $fresh = new ThresholdService;

        $this->assertEquals('15000', $fresh->get('approval', 'auto_approve'));
        $this->assertEquals(
            '10000',
            config('thresholds.approval.auto_approve'),
            'get() must not write persisted overrides into the config repository'
        );
    }

    #[Test]
    public function set_normalizes_uppercase_keys(): void
    {
        $this->actingAs($this->adminUser());

        $this->service->set('position_limits', 'USD', '2000000', 'raise cap');

        $audit = ThresholdAudit::where('category', 'position_limits')->where('key', 'usd')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('2000000', $audit->new_value);
    }

    #[Test]
    public function get_normalizes_uppercase_keys(): void
    {
        $this->assertEquals('1000000', $this->service->get('position_limits', 'USD'));
    }

    #[Test]
    public function reset_appends_reverting_audit_row(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        $this->service->set('approval', 'auto_approve', '15000', 'temporary raise');

        $result = $this->service->reset('approval', 'auto_approve', 'back to standard');

        $this->assertTrue($result);
        $this->assertEquals('10000', $this->service->get('approval', 'auto_approve'));

        $latest = ThresholdAudit::where('category', 'approval')
            ->where('key', 'auto_approve')
            ->latest('id')
            ->first();

        $this->assertEquals('15000', $latest->old_value);
        $this->assertEquals('10000', $latest->new_value);
        $this->assertEquals('back to standard', $latest->change_reason);
        $this->assertEquals(2, ThresholdAudit::where('category', 'approval')->where('key', 'auto_approve')->count());
    }

    #[Test]
    public function reset_returns_false_when_no_override_or_already_default(): void
    {
        $this->actingAs($this->adminUser());

        $this->assertFalse($this->service->reset('approval', 'auto_approve'));

        $this->service->set('approval', 'auto_approve', '15000');
        $this->service->reset('approval', 'auto_approve');

        $this->assertFalse($this->service->reset('approval', 'auto_approve'));
    }

    #[Test]
    public function reset_rejects_unknown_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->reset('approval', 'not_a_key');
    }

    #[Test]
    public function thresholdable_trait_covers_every_service_getter(): void
    {
        $interface = new \ReflectionClass(ThresholdService::class);
        $trait = new \ReflectionClass(Thresholdable::class);

        foreach ($interface->getMethods() as $method) {
            // 'get' is the low-level accessor; domain getters all use getX
            // names. Only public methods are delegated — protected internals
            // (e.g. getPersistedValue) are not part of the trait's surface.
            if (! $method->isPublic() || ! str_starts_with($method->getName(), 'get') || $method->getName() === 'get') {
                continue;
            }

            $this->assertTrue(
                $trait->hasMethod($method->getName()),
                "Thresholdable is missing a delegate for {$method->getName()}()"
            );
        }
    }

    private function adminUser()
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
