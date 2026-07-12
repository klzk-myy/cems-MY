<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionCreationServiceInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function store_returns_transaction_resource()
    {
        $branch = Branch::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = Counter::factory()->create(['code' => 'MAIN', 'branch_id' => $branch->id]);
        $customer = Customer::factory()->create();
        $tillBalance = TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'branch_id' => $branch->id,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'mfa_enabled' => false]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'currency_code' => $currency->code,
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
        ]);

        $creationService = $this->mock(TransactionCreationServiceInterface::class);
        $creationService->shouldReceive('prepareAndCreate')
            ->once()
            ->andReturn($transaction);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => $currency->code,
                'amount_foreign' => $transaction->amount_foreign,
                'rate' => $transaction->rate,
                'purpose' => $transaction->purpose,
                'source_of_funds' => $transaction->source_of_funds,
                'till_id' => $counter->code,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Transaction created successfully.')
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.customer_id', $transaction->customer_id)
            ->assertJsonPath('data.status', $transaction->status->value);
    }

    #[Test]
    public function show_returns_transaction_with_loaded_relations()
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();
        $teller = User::factory()->create(['branch_id' => $branch->id, 'role' => 'teller']);
        $approver = User::factory()->create(['branch_id' => $branch->id, 'role' => 'manager']);
        Currency::factory()->create(['code' => 'USD']);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'approved_by' => $approver->id,
            'till_id' => $counter->code,
            'currency_code' => 'USD',
        ]);

        FlaggedTransaction::factory()->create([
            'transaction_id' => $transaction->id,
        ]);

        $response = $this->actingAs($teller)
            ->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.customer_id', $transaction->customer_id)
            ->assertJsonPath('data.user_id', $transaction->user_id)
            ->assertJsonPath('data.branch_id', $transaction->branch_id)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.user.id', $teller->id)
            ->assertJsonPath('data.user.username', $teller->username)
            ->assertJsonPath('data.approver.id', $approver->id)
            ->assertJsonCount(1, 'data.flags');
    }
}
