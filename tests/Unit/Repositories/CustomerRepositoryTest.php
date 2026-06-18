<?php

namespace Tests\Unit\Repositories;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CustomerRepository;
    }

    #[Test]
    public function find_by_id_returns_customer(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->repository->findById($customer->id);

        $this->assertNotNull($result);
        $this->assertEquals($customer->id, $result->id);
    }

    #[Test]
    public function find_by_id_returns_null_for_missing_customer(): void
    {
        $result = $this->repository->findById(999999);

        $this->assertNull($result);
    }

    #[Test]
    public function find_by_id_number_returns_customer(): void
    {
        $idNumber = '900101-01-1234';
        $customer = Customer::factory()->create([
            'id_number_hash' => CustomerService::computeBlindIndex($idNumber),
        ]);

        $result = $this->repository->findByIdNumber($idNumber);

        $this->assertNotNull($result);
        $this->assertEquals($customer->id, $result->id);
    }

    #[Test]
    public function search_returns_customers_by_name(): void
    {
        Customer::factory()->create(['full_name' => 'Ahmad Bin Ismail']);
        Customer::factory()->create(['full_name' => 'Sarah Tan']);

        $results = $this->repository->search('Ahmad');

        $this->assertCount(1, $results);
        $this->assertEquals('Ahmad Bin Ismail', $results->first()->full_name);
    }

    #[Test]
    public function get_by_ids_returns_matching_customers(): void
    {
        $customers = Customer::factory()->count(3)->create();
        $ids = $customers->pluck('id')->take(2)->toArray();

        $results = $this->repository->getByIds($ids);

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($ids, $results->pluck('id')->toArray());
    }

    #[Test]
    public function get_customers_needing_rescreening_returns_high_risk_customers(): void
    {
        $highRisk = Customer::factory()->create(['risk_score' => 75, 'risk_assessed_at' => now()->subDays(10)]);
        Customer::factory()->create(['risk_score' => 30, 'risk_assessed_at' => now()->subDays(10)]);

        $results = $this->repository->getCustomersNeedingRescreening();

        $this->assertTrue($results->contains('id', $highRisk->id));
    }

    #[Test]
    public function get_customers_needing_rescreening_returns_stale_customers(): void
    {
        $stale = Customer::factory()->create(['risk_score' => 30, 'risk_assessed_at' => now()->subDays(31)]);
        Customer::factory()->create(['risk_score' => 30, 'risk_assessed_at' => now()->subDays(5)]);

        $results = $this->repository->getCustomersNeedingRescreening();

        $this->assertTrue($results->contains('id', $stale->id));
    }
}
