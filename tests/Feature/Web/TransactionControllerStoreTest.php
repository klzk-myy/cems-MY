<?php

namespace Tests\Feature\Web;

use App\Enums\PepType;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

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
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('web_', true),
        ];
    }

    #[Test]
    public function web_store_creates_completed_buy_transaction(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $response = $this->post('/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $currency->code,
            'quantity' => '100.00',
            'amount_myr' => '450.0000',
            'status' => TransactionStatus::Completed->value,
        ]);
    }

    #[Test]
    public function web_store_holds_large_transaction_for_approval(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD', '500000.0000');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $payload = $this->basePayload($customer, $counter, $currency);
        $payload['quantity'] = '2500.00';
        $payload['rate'] = '4.500000';

        $response = $this->post('/transactions', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'quantity' => '2500.00',
            'amount_myr' => '11250.0000',
            'status' => TransactionStatus::PendingApproval->value,
        ]);
    }

    #[Test]
    public function web_store_blocks_pep_transaction_without_source_of_wealth(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => true, 'pep_type' => PepType::Domestic->value]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $response = $this->post('/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertSessionHasErrors('source_of_wealth');
        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function web_store_creates_pep_transaction_with_source_of_wealth(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => true, 'pep_type' => PepType::Domestic->value]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $payload = $this->basePayload($customer, $counter, $currency);
        $payload['source_of_wealth'] = 'Investment Portfolio';

        $response = $this->post('/transactions', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Investment Portfolio',
        ]);
    }

    #[Test]
    public function web_store_rejects_source_of_wealth_over_500_characters(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => false]);
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $payload = $this->basePayload($customer, $counter, $currency);
        $payload['source_of_wealth'] = str_repeat('a', 501);

        $response = $this->post('/transactions', $payload);

        $response->assertSessionHasErrors('source_of_wealth');
        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function web_store_returns_error_for_blocked_customer(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create(['risk_rating' => 'low', 'pep_status' => false]);
        $customer->forceFill(['sanction_hit' => true])->save();
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = $this->setupStoreTest($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $response = $this->post('/transactions', $this->basePayload($customer, $counter, $currency));

        $response->assertSessionHasErrors('customer_id');
        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
    }
}
