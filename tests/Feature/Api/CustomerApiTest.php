<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_delegates_to_customer_service()
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
            ->andReturn(Customer::factory()->make([
                'id' => 1,
                'full_name' => 'John Doe',
            ]));

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/v1/customers', [
                'full_name' => 'John Doe',
                'id_type' => 'MyKad',
                'id_number' => '900123-01-2345',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'Malaysian',
                'risk_rating' => 'Low',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Customer created successfully.')
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.full_name', 'John Doe');
    }

    #[Test]
    public function update_delegates_to_customer_service()
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
                'id_number' => '900123-01-2345',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'Malaysian',
                'risk_rating' => 'Low',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Customer updated successfully.')
            ->assertJsonPath('data.id', $customer->id);
    }

    #[Test]
    public function show_returns_customer_with_transaction_stats_and_loaded_relations()
    {
        $customer = Customer::factory()->create();
        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'amount_local' => 100.00,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.full_name', $customer->full_name)
            ->assertJsonPath('transaction_stats.total_transactions', 2)
            ->assertJsonPath('transaction_stats.total_volume', 200)
            ->assertJsonCount(0, 'data.documents')
            ->assertJsonCount(2, 'data.transactions');
    }

    #[Test]
    public function show_returns_404_for_missing_customer()
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/v1/customers/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Customer not found.');
    }

    #[Test]
    public function customer_history_returns_transaction_collection()
    {
        $customer = Customer::factory()->create();
        $olderTransaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subDay(),
        ]);
        $newerTransaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson("/api/v1/customers/{$customer->id}/history");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerTransaction->id)
            ->assertJsonPath('data.1.id', $olderTransaction->id);
    }
}
