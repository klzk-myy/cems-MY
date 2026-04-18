<?php

namespace Tests\Feature;

use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF for tests
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Ensure core currencies exist
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);
    }

    public function test_teller_can_access_transaction_create(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/transactions/create');

        $response->assertStatus(200);
    }

    public function test_can_view_transaction_list(): void
    {
        $user = User::factory()->create();
        $customer = $this->createTestCustomer();

        $response = $this->actingAs($user)->get('/transactions');

        $response->assertStatus(200);
    }

    /**
     * Test teller can create buy transaction
     */
    public function test_teller_can_create_buy_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $this->actingAs($teller)->withSession([
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
        ]);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'status' => TransactionStatus::Completed,
        ]);
    }

    public function test_sell_updates_currency_position(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup initial position
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'branch_id' => $counter->branch_id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('currency_positions', [
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '400.00',
        ]);
    }

    public function test_buy_updates_currency_position(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup initial position
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'branch_id' => $counter->branch_id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Investment',
            'source_of_funds' => 'Salary',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('currency_positions', [
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '600.00',
        ]);
    }

    public function test_sell_fails_with_insufficient_stock(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup low initial position
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'branch_id' => $counter->branch_id,
            'balance' => '50.00',
            'avg_cost_rate' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHas('error'); // TransactionService throws exception which is caught and put in flash error
        $this->assertDatabaseMissing('transactions', [
            'type' => TransactionType::Sell,
            'amount_foreign' => '100.00',
        ]);
    }

    public function test_transaction_requires_positive_amount(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '-100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasErrors('amount_foreign');
    }

    public function test_transaction_requires_valid_currency(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'INVALID',
            'amount_foreign' => '1000',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasErrors('currency_code');
    }

    public function test_large_transaction_requires_approval(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        // threshold is 50,000 MYR. At 4.5 rate, 12,000 USD is 54,000 MYR
        // Need openingBalance > 54000 MYR for allocation to pass
        $counter = $this->setupOpenTill($teller, 'USD', '60000.00');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '12000.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Business',
            'source_of_funds' => 'Revenue',
            'till_id' => (string) $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'amount_foreign' => '12000.00',
            'status' => TransactionStatus::PendingApproval,
        ]);
    }

    public function test_teller_cannot_approve_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $response = $this->actingAs($teller)->post("/transactions/{$transaction->id}/approve");

        $response->assertStatus(403);
    }

    public function test_manager_can_approve_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        // Create a pending transaction
        $transaction = Transaction::create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '12000.00',
            'rate' => '4.50',
            'amount_local' => '54000.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::PendingApproval,
            'cdd_level' => 'Enhanced',
            'purpose' => 'Business',
            'source_of_funds' => 'Revenue',
            'idempotency_key' => uniqid('test_', true),
            'version' => 0,
        ]);

        // Create stock reservation for the transaction (required for approval flow)
        StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'amount_foreign' => '12000.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $teller->id,
        ]);

        // Create currency position with sufficient balance
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '15000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/approve");

        // If redirect back with error, capture it
        if ($response->isRedirect() && session('error')) {
            $this->fail('Approval failed with error: ' . session('error'));
        }

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::Completed,
            'approved_by' => $manager->id,
        ]);
    }
}
