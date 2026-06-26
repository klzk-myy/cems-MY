<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Customer\CustomerService;
use App\Services\System\MathService;
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
}
