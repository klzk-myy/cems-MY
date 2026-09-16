<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Cache\TaggableStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerServiceCacheTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function get_customer_uses_cache()
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

    #[Test]
    public function update_customer_invalidates_cache()
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

        // CacheInvalidationService calls Cache::getStore() to decide whether the store
        // supports tags, then Cache::tags(...)->flush() to invalidate 'dashboard'
        // and 'customers' (the cached nationality list lives there). The model
        // event safety net flushes both tags again on Customer::saved after the
        // transaction commits, so each tag can be flushed more than once per update.
        $taggableStore = Mockery::mock(TaggableStore::class);
        Cache::shouldReceive('getStore')->atLeast()->once()->andReturn($taggableStore);

        $dashboardMock = Mockery::mock();
        $dashboardMock->shouldReceive('flush')->atLeast()->once();
        Cache::shouldReceive('tags')->with(['dashboard'])->atLeast()->once()->andReturn($dashboardMock);

        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('flush')->atLeast()->once();
        Cache::shouldReceive('tags')->with(['customers'])->atLeast()->once()->andReturn($customersMock);

        // RiskScoringEngine caches the high-risk country code list (one read per recalculation)
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                'high_risk_country_codes',
                Mockery::type(\DateTimeInterface::class),
                Mockery::type(\Closure::class)
            )
            ->andReturn([]);

        // Expect customer cache forget
        Cache::shouldReceive('forget')
            ->once()
            ->with("customer:{$customer->id}");

        $service = app(CustomerService::class);
        $user = User::factory()->create();
        $service->updateCustomer($customer, ['full_name' => 'Updated Name'], $user->id);
    }
}
