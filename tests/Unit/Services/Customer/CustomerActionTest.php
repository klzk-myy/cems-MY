<?php

namespace Tests\Unit\Services\Customer;

use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function create_customer_action_delegates_to_create_customer_and_returns_customer(): void
    {
        $service = Mockery::mock(CustomerService::class)->makePartial();
        $data = ['full_name' => 'Alice Smith'];
        $createdBy = 5;
        $customer = new Customer(['full_name' => 'Alice Smith']);

        $service->shouldReceive('createCustomer')
            ->once()
            ->with($data, $createdBy)
            ->andReturn($customer);

        $result = $service->createCustomerAction($data, $createdBy);

        $this->assertSame($customer, $result);
    }

    #[Test]
    public function update_customer_action_delegates_to_update_customer_and_returns_refreshed_customer(): void
    {
        $service = Mockery::mock(CustomerService::class)->makePartial();
        $customer = new Customer(['id' => 1, 'full_name' => 'Alice Smith']);
        $data = ['full_name' => 'Alice Smith-Updated'];
        $updatedBy = 7;
        $updated = new Customer(['id' => 1, 'full_name' => 'Alice Smith-Updated']);

        $service->shouldReceive('updateCustomer')
            ->once()
            ->with($customer, $data, $updatedBy)
            ->andReturn($updated);

        $result = $service->updateCustomerAction($customer, $data, $updatedBy);

        $this->assertSame($updated, $result);
    }

    #[Test]
    public function create_customer_action_bubbles_exceptions_from_underlying_method(): void
    {
        $service = Mockery::mock(CustomerService::class)->makePartial();
        $service->shouldReceive('createCustomer')
            ->once()
            ->andThrow(new \RuntimeException('creation failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('creation failed');

        $service->createCustomerAction([], 1);
    }

    #[Test]
    public function update_customer_action_bubbles_exceptions_from_underlying_method(): void
    {
        $service = Mockery::mock(CustomerService::class)->makePartial();
        $service->shouldReceive('updateCustomer')
            ->once()
            ->andThrow(new \RuntimeException('update failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('update failed');

        $service->updateCustomerAction(new Customer, [], 1);
    }
}
