<?php

namespace Tests\Unit\Repositories;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CustomerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CustomerRepository;
    }

    public function test_find_by_id_returns_customer(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->repository->findById($customer->id);

        $this->assertNotNull($result);
        $this->assertEquals($customer->id, $result->id);
    }

    public function test_find_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->repository->findById(999999);

        $this->assertNull($result);
    }

    public function test_find_by_id_number_returns_customer(): void
    {
        $customer = Customer::factory()->create([
            'id_number_encrypted' => 'encrypted_value',
            'id_number_hash' => CustomerService::computeBlindIndex('123456789012'),
        ]);

        $result = $this->repository->findByIdNumber('123456789012');

        $this->assertNotNull($result);
        $this->assertEquals($customer->id, $result->id);
    }

    public function test_find_by_id_number_returns_null_for_nonexistent(): void
    {
        $result = $this->repository->findByIdNumber('nonexistent');

        $this->assertNull($result);
    }

    public function test_search_returns_matching_customers(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'John Doe']);

        $result = $this->repository->search('John');

        $this->assertCount(1, $result);
        $this->assertEquals($customer->id, $result->first()->id);
    }

    public function test_search_returns_multiple_matches(): void
    {
        Customer::factory()->create(['full_name' => 'John Doe']);
        Customer::factory()->create(['full_name' => 'John Smith']);

        $result = $this->repository->search('John');

        $this->assertCount(2, $result);
    }

    public function test_search_returns_empty_for_no_matches(): void
    {
        $result = $this->repository->search('xyzabc');

        $this->assertCount(0, $result);
    }

    public function test_get_by_ids_returns_customers(): void
    {
        $customers = Customer::factory()->count(3)->create();
        $ids = $customers->pluck('id')->toArray();

        $result = $this->repository->getByIds($ids);

        $this->assertCount(3, $result);
    }

    public function test_get_by_ids_returns_empty_for_no_matches(): void
    {
        $result = $this->repository->getByIds([999999, 999998]);

        $this->assertCount(0, $result);
    }

    public function test_get_customers_needing_rescreening_by_risk_score(): void
    {
        Customer::factory()->create(['risk_score' => 59]);
        Customer::factory()->create(['risk_score' => 60]);
        Customer::factory()->create(['risk_score' => 75]);

        $result = $this->repository->getCustomersNeedingRescreening();

        $this->assertCount(2, $result);
    }

    public function test_get_customers_needing_rescreening_by_assessment_date(): void
    {
        Customer::factory()->create(['risk_score' => 50, 'risk_assessed_at' => now()->subDays(29)]);
        Customer::factory()->create(['risk_score' => 50, 'risk_assessed_at' => now()->subDays(31)]);
        Customer::factory()->create(['risk_score' => 50, 'risk_assessed_at' => now()->subDays(60)]);

        $result = $this->repository->getCustomersNeedingRescreening();

        $this->assertCount(2, $result);
    }
}
