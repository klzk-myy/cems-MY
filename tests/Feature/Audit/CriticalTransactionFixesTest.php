<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Customer\CustomerService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalTransactionFixesTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = app(MathService::class);
    }

    public function test_customer_not_flagged_when_screening_is_clear(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->for($branch)->create();
        $this->actingAs($user);

        $service = app(CustomerService::class);
        $customer = Customer::factory()->create(['is_active' => true]);

        $method = new \ReflectionMethod($service, 'screenCustomer');
        $method->setAccessible(true);
        $method->invoke($service, $customer, 'Clean Name');

        $this->assertTrue($customer->fresh()->is_active);
        $this->assertFalse($customer->fresh()->sanction_hit);
    }

    public function test_buy_transaction_decreases_myr_till_balance(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create();
        $till = Counter::factory()->for($branch)->create();

        TillBalance::factory()->for($till)->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'date' => today(),
            'transaction_total' => '0',
        ]);
        TillBalance::factory()->for($till)->create([
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'date' => today(),
            'transaction_total' => '1000.00',
        ]);

        $this->actingAs($teller);

        $service = app(TransactionServiceInterface::class);
        $service->createTransaction([
            'customer_id' => $customer->id,
            'till_id' => $till->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '470.00',
            'rate' => '4.70',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
        ], $teller->id, '127.0.0.1');

        $this->assertSame('530.0000', TillBalance::where('till_id', $till->id)->where('currency_code', 'MYR')->first()->transaction_total);
    }

    public function test_reversing_buy_restores_myr_till_balance(): void
    {
        $transaction = $this->createCompletedBuyTransaction();
        $before = TillBalance::where('till_id', $transaction->till_id)->where('currency_code', 'MYR')->first()->transaction_total;

        $manager = User::factory()->for($transaction->branch)->create(['role' => UserRole::Manager]);
        $service = app(TransactionReversalService::class);
        $service->reverse($transaction, $manager, 'customer request');

        $after = TillBalance::where('till_id', $transaction->till_id)->where('currency_code', 'MYR')->first()->transaction_total;
        $this->assertSame(
            $this->mathService->add($before, $transaction->amount_local),
            $after
        );
    }

    private function createCompletedBuyTransaction(): Transaction
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create();
        $till = Counter::factory()->for($branch)->create();

        TillBalance::factory()->for($till)->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'date' => today(),
            'transaction_total' => '0',
        ]);
        TillBalance::factory()->for($till)->create([
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'date' => today(),
            'transaction_total' => '1000.00',
        ]);

        $transaction = Transaction::factory()->buy()->create([
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'till_id' => $till->id,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '470.00',
            'rate' => '4.70',
        ]);

        $transaction->status = TransactionStatus::Completed;
        $transaction->save();

        return $transaction;
    }

    public function test_confirmation_page_loads_without_enum_error(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $transaction = Transaction::factory()->for($branch)->state([
            'status' => TransactionStatus::PendingApproval,
            'amount_local' => '75000.00',
        ])->create();
        TransactionConfirmation::factory()->for($transaction)->create([
            'status' => TransactionConfirmationStatus::Pending,
        ]);

        $this->actingAs($manager)
            ->get(route('transactions.confirm.show', $transaction))
            ->assertOk();
    }

    public function test_reversal_does_not_create_refund_when_transition_fails(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $transaction = Transaction::factory()->for($branch)->create();
        $transaction->status = TransactionStatus::Reversed;
        $transaction->save();

        $service = app(TransactionReversalService::class);

        $this->expectException(\RuntimeException::class);
        $service->reverse($transaction, $manager, 'oops');

        $this->assertSame(0, Transaction::where('is_refund', true)->count());
    }

    public function test_admin_can_request_cancellation(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->for($branch)->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->for($branch)->state([
            'status' => TransactionStatus::Completed,
        ])->create();
        $transaction->status = TransactionStatus::Completed;
        $transaction->save();

        $service = app(TransactionCancellationService::class);
        $this->assertTrue($service->requestCancellation($transaction, $admin, 'reason'));
        $this->assertSame(TransactionStatus::PendingCancellation->value, $transaction->fresh()->status->value);
    }

    public function test_admin_can_access_cancel_form(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->for($branch)->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->for($branch)->create();

        $this->actingAs($admin)
            ->get(route('transactions.cancel', $transaction))
            ->assertOk();
    }
}
