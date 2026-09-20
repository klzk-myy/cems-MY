<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CounterSessionStatus;
use App\Enums\PepType;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    private function setupStoreTest(User $teller, string $currencyCode = 'USD', string $allocationBalance = '100000.0000'): Counter
    {
        $branch = $teller->branch;
        $counter = Counter::factory()->create([
            'branch_id' => $branch->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
            'currency_code' => $currencyCode,
            'date' => today(),
            'closed_at' => null,
            'opened_by' => $teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'date' => today(),
            'closed_at' => null,
            'opened_by' => $teller->id,
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => $currencyCode,
            'allocated_quantity' => $allocationBalance,
            'current_quantity' => $allocationBalance,
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => today(),
        ]);

        return $counter;
    }

    private function basePayload(Customer $customer, Counter $counter, Currency $currency): array
    {
        return [
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'till_id' => (string) $counter->code,
        ];
    }

    #[Test]
    public function api_store_creates_completed_buy_transaction(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', TransactionStatus::Completed->value);
        $response->assertJsonPath('data.amount_myr', '450.0000');
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'quantity' => '100.00',
            'amount_myr' => '450.0000',
            'status' => TransactionStatus::Completed->value,
        ]);
    }

    #[Test]
    public function api_store_holds_large_transaction_for_approval(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD', '500000.0000');

        $payload = $this->basePayload($customer, $counter, $currency);
        $payload['quantity'] = '2500.00';
        $payload['rate'] = '4.500000';

        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', TransactionStatus::PendingApproval->value);
        $response->assertJsonPath('data.amount_myr', '11250.0000');
    }

    #[Test]
    public function api_store_returns_blocked_error_for_sanctioned_customer(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $customer->forceFill(['sanction_hit' => true])->save();
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function api_store_blocks_pep_transaction_without_source_of_wealth(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => true, 'pep_type' => PepType::Domestic->value]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function api_store_creates_pep_transaction_with_source_of_wealth(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => true, 'pep_type' => PepType::Domestic->value]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $payload = $this->basePayload($customer, $counter, $currency);
        $payload['source_of_wealth'] = 'Investment Portfolio';

        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Investment Portfolio',
        ]);
    }

    #[Test]
    public function api_and_web_stores_produce_identical_transaction_state(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $apiPayload = $this->basePayload($customer, $counter, $currency);
        $apiPayload['idempotency_key'] = uniqid('api_', true);

        $apiResponse = $this->actingAs($teller)->postJson('/api/v1/transactions', $apiPayload);
        $apiResponse->assertStatus(201);
        $apiTransaction = $apiResponse->json('data');

        $webTeller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $this->setupStoreTest($webTeller, 'USD');
        $webCustomer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $webPayload = $this->basePayload($webCustomer, $counter, $currency);
        $webPayload['branch_id'] = $counter->branch_id;
        $webPayload['counter_id'] = $counter->id;
        $webPayload['idempotency_key'] = uniqid('web_', true);
        unset($webPayload['till_id']);

        // The API call above ran under auth:sanctum, which leaves sanctum as
        // the default guard. Pin the web guard explicitly so auth.session's
        // AuthenticateSession middleware resolves a SessionGuard (RequestGuard
        // has no viaRemember()).
        $this->actingAs($webTeller, 'web');
        $this->setMfaVerification($webTeller);
        $webResponse = $this->post('/transactions', $webPayload);
        $webResponse->assertSessionHasNoErrors();
        $webResponse->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $apiTransaction['id'],
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => '450.0000',
        ]);

        $webTransaction = Transaction::where('customer_id', $webCustomer->id)->first();
        $this->assertNotNull($webTransaction);
        $this->assertEquals($apiTransaction['status'], $webTransaction->status->value);
        $this->assertEquals($apiTransaction['amount_myr'], $webTransaction->amount_myr);
        $this->assertEquals($apiTransaction['type'], $webTransaction->type->value);
    }

    /**
     * Session counter wins on the API too: a teller seated at one counter
     * cannot book against another till by submitting its code.
     */
    #[Test]
    public function api_store_session_counter_overrides_submitted_till(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $sessionCounter = $this->setupStoreTest($teller, 'USD');

        CounterSession::factory()->create([
            'counter_id' => $sessionCounter->id,
            'user_id' => $teller->id,
            'opened_by' => $teller->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);

        $otherCounter = Counter::factory()->create(['branch_id' => $branch->id]);

        $payload = $this->basePayload($customer, $otherCounter, $currency);
        $response = $this->actingAs($teller)->postJson('/api/v1/transactions', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'till_id' => $sessionCounter->code,
            'counter_id' => $sessionCounter->id,
        ]);
    }
}
