<?php

namespace Tests\Feature\Audit;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
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
}
