<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CustomerServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_customer_uses_cache()
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456'),
            'date_of_birth' => '1990-01-01',
            'nationality' => 'MY',
            'address' => encrypt('123 Street'),
            'phone' => encrypt('0123456789'),
            'email' => 'test@example.com',
            'risk_rating' => 'Low',
            'pep_status' => false,
            'sanction_hit' => false,
            'is_active' => true,
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($customer);

        $service = app(CustomerService::class);
        $result = $service->getCustomer($customer->id);

        $this->assertEquals($customer->id, $result->id);
    }

    public function test_update_customer_invalidates_cache()
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456'),
            'date_of_birth' => '1990-01-01',
            'nationality' => 'MY',
            'address' => encrypt('123 Street'),
            'phone' => encrypt('0123456789'),
            'email' => 'test@example.com',
            'risk_rating' => 'Low',
            'pep_status' => false,
            'sanction_hit' => false,
            'is_active' => true,
        ]);

        // Mock dashboard tag flush (called by cacheTagsService->invalidate('dashboard'))
        $dashboardMock = Mockery::mock();
        $dashboardMock->shouldReceive('flush')->once();
        Cache::shouldReceive('tags')->with(['dashboard'])->once()->andReturn($dashboardMock);

        // Expect customer cache forget
        Cache::shouldReceive('forget')
            ->once()
            ->with("customer:{$customer->id}");

        $service = app(CustomerService::class);
        $user = User::factory()->create();
        $service->updateCustomer($customer, ['full_name' => 'Updated Name'], $user->id);
    }
}
