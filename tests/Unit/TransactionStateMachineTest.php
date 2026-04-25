<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TransactionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected User $user;

    protected Currency $currency;

    protected Branch $branch;

    protected Counter $counter;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService;

        $this->user = User::factory()->create(['role' => UserRole::Admin]);

        $this->branch = Branch::create([
            'code' => 'HQ-TEST',
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::create([
            'name' => 'Test Counter',
            'code' => 'CTR-TEST',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->customer = Customer::create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-15',
            'risk_rating' => 'Low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);
    }

    protected function createTransaction(TransactionStatus $status = TransactionStatus::Draft): Transaction
    {
        return Transaction::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy,
            'currency_code' => $this->currency->code,
            'amount_local' => '1000.00',
            'amount_foreign' => '285.7143',
            'rate' => '3.50',
            'status' => $status->value,
            'cdd_level' => 'Simplified',
            'transaction_date' => now()->toDateString(),
        ]);
    }

    public function test_force_status_logs_to_audit_trail(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);

        // Manually set auth for this test since forceStatus uses auth()->id()
        auth()->login($this->user);

        $stateMachine = new TransactionStateMachine($transaction, $this->auditService);

        $result = $stateMachine->forceStatus(TransactionStatus::Completed, 'Admin override for testing');

        $this->assertTrue($result);

        // Verify audit log entry was created
        $auditEntry = SystemLog::where('entity_type', 'Transaction')
            ->where('entity_id', $transaction->id)
            ->where('action', 'force_status_override')
            ->first();

        $this->assertNotNull($auditEntry, 'Audit entry should be created for force_status_override');
        $this->assertEquals($transaction->id, $auditEntry->entity_id);
        $this->assertEquals('Admin override for testing', $auditEntry->new_values['reason'] ?? $auditEntry->description);
        $this->assertEquals($this->user->id, $auditEntry->user_id);
    }

    public function test_force_status_without_audit_service_still_saves(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);

        // Pass null as audit service (backwards compatible)
        $stateMachine = new TransactionStateMachine($transaction, null);

        $result = $stateMachine->forceStatus(TransactionStatus::Cancelled, 'Force cancel without audit');

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Cancelled, $transaction->status);
    }

    public function test_release_method_has_been_removed(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);
        $stateMachine = new TransactionStateMachine($transaction);

        // The release() method was removed because it was dead code - it transitioned
        // PendingApproval -> PendingApproval which is a no-op and was never called
        $this->assertFalse(
            method_exists($stateMachine, 'release'),
            'release() method should not exist on TransactionStateMachine'
        );
    }
}
