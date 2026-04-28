<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_delegates_to_customer_service()
    {
        $customerService = $this->mock(CustomerService::class);
        $customerService->shouldReceive('createCustomer')
            ->once()
            ->with(
                \Mockery::on(function ($data) {
                    return isset($data['full_name']) && isset($data['id_number']);
                }),
                1
            )
            ->andReturn(Customer::factory()->make(['id' => 1]));

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/v1/customers', [
                'full_name' => 'John Doe',
                'id_type' => 'MyKad',
                'id_number' => '123456789012',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'Malaysian',
            ]);

        $response->assertStatus(201);
    }

    public function test_update_delegates_to_customer_service()
    {
        $customer = Customer::factory()->create();
        $customerService = $this->mock(CustomerService::class);
        $customerService->shouldReceive('updateCustomer')
            ->once()
            ->with(
                \Mockery::on(function ($c) use ($customer) {
                    return $c->id === $customer->id;
                }),
                \Mockery::on(function ($data) {
                    return isset($data['full_name']);
                }),
                1
            )
            ->andReturn($customer);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson("/api/v1/customers/{$customer->id}", [
                'full_name' => 'Jane Doe',
                'id_type' => 'MyKad',
                'id_number' => '123456789012',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'Malaysian',
            ]);

        $response->assertStatus(200);
    }
}
