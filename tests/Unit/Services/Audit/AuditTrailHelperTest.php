<?php

namespace Tests\Unit\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTrailHelperTest extends TestCase
{
    use RefreshDatabase;

    private AuditTrailHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->helper = new AuditTrailHelper(app(AuditService::class));
    }

    #[Test]
    public function record_creates_audit_trail_row(): void
    {
        $user = User::factory()->create();

        $auditTrail = $this->helper->record('Transaction', 123, 'created', ['amount' => 100], $user);

        $this->assertInstanceOf(AuditTrail::class, $auditTrail);
        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Transaction',
            'auditable_id' => 123,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function record_uses_explicit_ip_address_and_null_user(): void
    {
        $auditTrail = $this->helper->record('Customer', 456, 'viewed', [], null, '10.0.0.1');

        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Customer',
            'auditable_id' => 456,
            'action' => 'viewed',
            'user_id' => null,
            'ip_address' => '10.0.0.1',
        ]);
    }

    #[Test]
    public function record_transaction_creates_audit_trail_and_system_log_rows(): void
    {
        $user = User::factory()->create();

        $auditTrail = $this->helper->recordTransaction(
            789,
            'transaction_approved',
            [
                'old' => ['status' => 'pending'],
                'new' => ['status' => 'completed'],
            ],
            $user,
            'WARNING'
        );

        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Transaction',
            'auditable_id' => 789,
            'action' => 'transaction_approved',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'transaction_approved',
            'entity_type' => 'Transaction',
            'entity_id' => 789,
            'severity' => 'WARNING',
        ]);
    }

    #[Test]
    public function record_customer_creates_audit_trail_and_system_log_rows(): void
    {
        $user = User::factory()->create();

        $auditTrail = $this->helper->recordCustomer(
            321,
            'customer_updated',
            [
                'old' => ['risk_rating' => 'Low'],
                'new' => ['risk_rating' => 'High'],
            ],
            $user,
            'ERROR'
        );

        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Customer',
            'auditable_id' => 321,
            'action' => 'customer_updated',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'customer_updated',
            'entity_type' => 'Customer',
            'entity_id' => 321,
            'severity' => 'ERROR',
        ]);
    }

    #[Test]
    public function record_transaction_forwards_ip_address_and_user_id_to_logs(): void
    {
        $user = User::factory()->create();

        $auditTrail = $this->helper->recordTransaction(
            111,
            'transaction_viewed',
            [],
            $user,
            'INFO',
            '192.168.1.1'
        );

        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Transaction',
            'auditable_id' => 111,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'transaction_viewed',
            'entity_type' => 'Transaction',
            'entity_id' => 111,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function record_customer_forwards_ip_address_and_user_id_to_logs(): void
    {
        $user = User::factory()->create();

        $auditTrail = $this->helper->recordCustomer(
            222,
            'customer_viewed',
            [],
            $user,
            'INFO',
            '10.0.0.2'
        );

        $this->assertDatabaseHas('audit_trails', [
            'id' => $auditTrail->id,
            'auditable_type' => 'Customer',
            'auditable_id' => 222,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'customer_viewed',
            'entity_type' => 'Customer',
            'entity_id' => 222,
            'user_id' => $user->id,
        ]);
    }
}
