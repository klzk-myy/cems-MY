<?php

namespace Tests\Feature;

use App\Enums\SystemAlertLevel;
use App\Enums\UserRole;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\ComplianceCaseSlaBreachedNotification;
use App\Notifications\SystemHealthAlertNotification;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\RevaluationService;
use App\Services\AuditService;
use App\Services\Compliance\CaseManagementService;
use App\Services\Compliance\MonitoringEngine;
use App\Services\Compliance\SanctionsImportService;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use App\Services\Transaction\RateApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceQuickWinsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monitor_failure_raises_one_critical_alert_per_monitor_per_cycle(): void
    {
        $engine = app(MonitoringEngine::class);

        $failure = [
            'monitor' => 'App\Monitors\BrokenMonitor',
            'exception' => \RuntimeException::class,
            'message' => 'boom',
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->setProtected($engine, 'failureLog', [$failure, $failure]);

        $invoke = new \ReflectionMethod($engine, 'sendFailureNotification');
        $invoke->invoke($engine, 2, ['App\Monitors\BrokenMonitor']);
        // Second call within the same run-cycle must be throttled.
        $invoke->invoke($engine, 2, ['App\Monitors\BrokenMonitor']);

        $this->assertDatabaseCount('system_alerts', 1);
        $alert = SystemAlert::sole();
        $this->assertEquals(SystemAlertLevel::Critical->value, $alert->level->value);
        $this->assertStringContainsString('BrokenMonitor', $alert->message);
        $encodedMetadata = json_encode($alert->metadata);
        $this->assertNotFalse($encodedMetadata);
        $this->assertStringContainsString('boom', $encodedMetadata);

        // A fresh run-cycle (cleared log) alerts again.
        $engine->clearFailureLog();
        $this->setProtected($engine, 'failureLog', [$failure]);
        $invoke->invoke($engine, 1, ['App\Monitors\BrokenMonitor']);

        $this->assertDatabaseCount('system_alerts', 2);
    }

    #[Test]
    public function monitor_failure_sends_health_alert_notification_to_officers(): void
    {
        Notification::fake();

        $officer = User::factory()->complianceOfficer()->create();
        User::factory()->teller()->create();

        $engine = app(MonitoringEngine::class);

        $failure = [
            'monitor' => 'App\Monitors\BrokenMonitor',
            'exception' => \RuntimeException::class,
            'message' => 'boom',
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->setProtected($engine, 'failureLog', [$failure, $failure]);

        $invoke = new \ReflectionMethod($engine, 'sendFailureNotification');
        $invoke->invoke($engine, 2, ['App\Monitors\BrokenMonitor']);
        // Throttled repeat must not duplicate the notification.
        $invoke->invoke($engine, 2, ['App\Monitors\BrokenMonitor']);

        Notification::assertSentToTimes($officer, SystemHealthAlertNotification::class, 1);
        Notification::assertNotSentTo(
            User::where('role', UserRole::Teller->value)->first(),
            SystemHealthAlertNotification::class
        );
    }

    #[Test]
    public function manual_sanctions_import_is_attributed_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $list = SanctionList::factory()->create();

        $this->actingAs($user);

        app(SanctionsImportService::class)->importWithData($list, ['results' => []], true);

        $log = SanctionImportLog::latest('id')->first();
        $this->assertEquals('manual', $log->triggered_by);
        $this->assertEquals($user->id, $log->user_id);
    }

    #[Test]
    public function scheduled_sanctions_import_records_scheduled_trigger_without_user(): void
    {
        $list = SanctionList::factory()->create();

        app(SanctionsImportService::class)->importWithData($list, ['results' => []], false);

        $log = SanctionImportLog::latest('id')->first();
        $this->assertEquals('scheduled', $log->triggered_by);
        $this->assertNull($log->user_id);
    }

    #[Test]
    public function position_limit_breach_over_ten_percent_raises_critical_alert(): void
    {
        Config::set('thresholds.position_limits', ['usd' => '100']);

        $service = $this->revaluationService();
        $method = new \ReflectionMethod($service, 'checkPositionLimitBreach');
        $method->invoke($service, ['currency' => 'USD', 'gain_loss' => '150'], 'HQ');

        $alert = SystemAlert::where('source', 'revaluation')->sole();
        $this->assertEquals(SystemAlertLevel::Critical->value, $alert->level->value);
        $this->assertEquals('USD', $alert->metadata['currency_code']);
        $this->assertEquals('HQ', $alert->metadata['branch_id']);
        $this->assertIsNumeric($alert->metadata['breach_amount']);
        $this->assertSame(0, bccomp((string) $alert->metadata['breach_amount'], '50', 4));
    }

    #[Test]
    public function small_position_limit_breach_raises_warning_alert(): void
    {
        Config::set('thresholds.position_limits', ['eur' => '1000']);

        $service = $this->revaluationService();
        $method = new \ReflectionMethod($service, 'checkPositionLimitBreach');
        $method->invoke($service, ['currency' => 'EUR', 'gain_loss' => '1050'], null);

        $alert = SystemAlert::where('source', 'revaluation')->sole();
        $this->assertEquals(SystemAlertLevel::Warning->value, $alert->level->value);
        $this->assertNull($alert->metadata['branch_id']);
    }

    #[Test]
    public function sla_breach_sweep_raises_single_summary_alert_and_notifies_assignees(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $otherAssignee = User::factory()->create();

        ComplianceCase::factory()->create([
            'status' => 'Open',
            'priority' => 'Critical',
            'assigned_to' => $assignee->id,
            'sla_deadline' => now()->subHours(30),
        ]);
        ComplianceCase::factory()->create([
            'status' => 'UnderReview',
            'priority' => 'Low',
            'assigned_to' => $otherAssignee->id,
            'sla_deadline' => now()->subHours(2),
        ]);

        $result = app(CaseManagementService::class)->alertBreachedCases();

        $this->assertEquals(2, $result['breached']);
        $this->assertEquals(1, $result['by_priority']['Critical'] ?? 0);
        $this->assertEquals(2, $result['notified']);

        Notification::assertSentTo($assignee, ComplianceCaseSlaBreachedNotification::class);
        Notification::assertSentTo($otherAssignee, ComplianceCaseSlaBreachedNotification::class);

        $alert = SystemAlert::where('source', 'case_sla_breach')->sole();
        $this->assertEquals(SystemAlertLevel::Critical->value, $alert->level->value);
    }

    #[Test]
    public function pep_cessation_command_downgrades_eligible_customers_and_respects_locks(): void
    {
        $eligible = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subYears(6),
            'current_role_domain' => 'finance',
            'former_pep_domain' => 'defence',
        ]);

        $lockedCustomer = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subYears(6),
            'current_role_domain' => 'finance',
            'former_pep_domain' => 'defence',
        ]);
        CustomerRiskProfile::factory()->create([
            'customer_id' => $lockedCustomer->id,
            'locked_until' => now()->addYear(),
            'lock_reason' => 'EDD review hold',
        ]);

        $recentPep = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subMonths(6),
            'current_role_domain' => 'finance',
            'former_pep_domain' => 'defence',
        ]);

        $this->artisanCommand('customers:pep-cessation-review')
            ->expectsOutputToContain('Cessated: 1')
            ->assertSuccessful();

        $this->assertFalse($eligible->fresh()->pep_status);
        $this->assertTrue($lockedCustomer->fresh()->pep_status, 'Locked profile must not be cessated');
        $this->assertTrue($recentPep->fresh()->pep_status, 'Recent PEP (< 2 years) must not cessate');
    }

    private function revaluationService(): object
    {
        return new RevaluationService(
            app(MathService::class),
            app(RateApiService::class),
            app(AccountingService::class),
            app(AuditService::class),
            app(SystemAlertService::class),
        );
    }

    private function setProtected(object $target, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($target, $property);
        $ref->setValue($target, $value);
    }
}
