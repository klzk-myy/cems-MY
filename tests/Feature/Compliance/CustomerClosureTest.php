<?php

namespace Tests\Feature\Compliance;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerClosureTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerService::class);
        $this->actor = User::factory()->create(['role' => 'manager']);
    }

    #[Test]
    public function close_is_blocked_while_transactions_await_approval(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        Transaction::factory()->for($customer)->create([
            'status' => TransactionStatus::PendingApproval->value,
        ]);

        $this->assertFalse($customer->canBeClosed());

        $this->expectException(ValidationException::class);

        $this->service->closeCustomer($customer, 'Customer requested closure', $this->actor);
    }

    #[Test]
    public function close_is_blocked_while_cancellation_is_pending(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        Transaction::factory()->for($customer)->create([
            'status' => TransactionStatus::PendingCancellation->value,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->closeCustomer($customer, 'Customer requested closure', $this->actor);
    }

    #[Test]
    public function close_succeeds_and_writes_audit_trail_when_no_blocking_transactions(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        Transaction::factory()->for($customer)->create([
            'status' => TransactionStatus::Completed->value,
        ]);

        $this->assertTrue($customer->canBeClosed());

        $closed = $this->service->closeCustomer($customer, 'Customer relocated overseas', $this->actor);

        $closed->refresh();

        $this->assertFalse($closed->is_active);
        $this->assertSame('Customer relocated overseas', $closed->closure_reason);
        $this->assertNotNull($closed->closed_at);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'customer_closed',
            'entity_type' => 'Customer',
            'entity_id' => $customer->id,
        ]);
    }
}
