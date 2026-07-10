<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function store_returns_transaction_resource()
    {
        $transaction = Transaction::factory()->create();

        $transactionService = $this->mock(TransactionService::class);
        $transactionService->shouldReceive('createTransaction')
            ->once()
            ->with(
                \Mockery::on(function ($data) {
                    return isset($data['customer_id']) && isset($data['currency_code']);
                }),
                \Mockery::any(),
                \Mockery::any()
            )
            ->andReturn($transaction);

        $teller = User::factory()->create([
            'role' => 'teller',
            'mfa_enabled' => false,
        ]);

        Counter::factory()->create(['code' => 'MAIN']);

        $response = $this->actingAs($teller)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $transaction->customer_id,
                'type' => 'Buy',
                'currency_code' => $transaction->currency_code,
                'amount_foreign' => $transaction->amount_foreign,
                'rate' => $transaction->rate,
                'purpose' => $transaction->purpose,
                'source_of_funds' => $transaction->source_of_funds,
                'till_id' => $transaction->till_id,
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
