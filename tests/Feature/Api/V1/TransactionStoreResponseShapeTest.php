<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStoreResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_transaction_returns_standardized_success_envelope(): void
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'teller',
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'date' => today(),
            'closed_at' => null,
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'date' => today(),
            'closed_at' => null,
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'allocated_amount' => '10000.00',
            'current_balance' => '10000.00',
            'daily_used_myr' => '0.00',
            'status' => TellerAllocationStatus::ACTIVE,
            'session_date' => today(),
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'type' => 'Buy',
            'currency_code' => $currency->code,
            'amount_foreign' => 100,
            'rate' => 1.5,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/transactions', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Transaction created successfully.',
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'customer_id', 'type', 'currency_code', 'amount_foreign'],
        ]);
    }
}
