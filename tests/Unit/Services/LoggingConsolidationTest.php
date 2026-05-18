<?php

namespace Tests\Unit\Services;

use App\Models\SystemLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoggingConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService;
        $this->user = User::factory()->create();
    }

    public function test_audit_service_logs_transaction_action(): void
    {
        $log = $this->auditService->logTransaction('test_action', 1, ['old' => ['a' => 1], 'new' => ['a' => 2]]);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('Transaction', $log->entity_type);
        $this->assertEquals(1, $log->entity_id);
    }

    public function test_audit_service_logs_customer_action(): void
    {
        $log = $this->auditService->logCustomer('customer_updated', 42, ['old' => ['name' => 'Old'], 'new' => ['name' => 'New']]);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('Customer', $log->entity_type);
        $this->assertEquals(42, $log->entity_id);
    }

    public function test_audit_service_log_returns_system_log_instance(): void
    {
        $log = $this->auditService->log('login_success', null, null, null);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('login_success', $log->action);
        $this->assertEquals('INFO', $log->severity);
    }

    public function test_audit_service_log_transaction_with_severity(): void
    {
        $log = $this->auditService->logTransaction('transfer_completed', 100, [
            'old' => ['status' => 'pending'],
            'new' => ['status' => 'completed'],
            'severity' => 'WARNING',
        ]);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('WARNING', $log->severity);
        $this->assertEquals('Transaction', $log->entity_type);
    }

    public function test_audit_service_log_mfa_event(): void
    {
        $log = $this->auditService->logMfaEvent('mfa_setup_completed', $this->user->id);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('MfaEvent', $log->entity_type);
        $this->assertEquals('mfa_setup_completed', $log->action);
    }

    public function test_audit_service_log_compliance_decision(): void
    {
        $log = $this->auditService->logComplianceDecision('flag_resolved', 55, [
            'old' => ['status' => 'open'],
            'new' => ['status' => 'resolved'],
        ]);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('Compliance', $log->entity_type);
        $this->assertEquals(55, $log->entity_id);
    }

    public function test_audit_service_log_session_event(): void
    {
        $log = $this->auditService->logSessionEvent('session_timeout', ['user_id' => $this->user->id]);

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('Session', $log->entity_type);
        $this->assertEquals('session_timeout', $log->action);
    }
}
