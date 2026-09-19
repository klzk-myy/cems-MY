<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF for tests
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Ensure core currencies exist
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);
    }

    #[Test]
    public function teller_can_access_transaction_create(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/transactions/create');

        $response->assertStatus(200);
    }

    #[Test]
    public function can_view_transaction_list(): void
    {
        $user = User::factory()->create();
        $customer = $this->createTestCustomer();

        $response = $this->actingAs($user)->get('/transactions');

        $response->assertStatus(200);
    }

    /**
     * Test teller can create buy transaction
     */
    #[Test]
    public function teller_can_create_buy_transaction(): void
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
            'quantity' => '100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'status' => TransactionStatus::Completed,
        ]);
    }

    #[Test]
    public function sell_updates_currency_position(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup initial position (positions are keyed by currency + branch)
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '500.00',
            'average_cost' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('currency_positions', [
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '400.00',
        ]);
    }

    #[Test]
    public function buy_updates_currency_position(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup initial position (positions are keyed by currency + branch)
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '500.00',
            'average_cost' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Investment',
            'source_of_funds' => 'Salary',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('currency_positions', [
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '600.00',
        ]);
    }

    #[Test]
    public function sell_fails_with_insufficient_stock(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        // Setup low initial position - 50 USD available, selling 100
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '50.00',
            'average_cost' => '4.40',
        ]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasErrors('quantity'); // InsufficientStockException maps to the amount field
        $this->assertDatabaseMissing('transactions', [
            'type' => TransactionType::Sell,
            'quantity' => '100.00',
        ]);
    }

    #[Test]
    public function transaction_requires_positive_amount(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '-100.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    #[Test]
    public function transaction_requires_valid_currency(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $response = $this->post('/transactions', [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'INVALID',
            'quantity' => '1000',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasErrors('currency_code');
    }

    #[Test]
    public function large_transaction_requires_approval(): void
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
            'quantity' => '12000.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'purpose' => 'Business',
            'source_of_funds' => 'Revenue',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'quantity' => '12000.00',
            'status' => TransactionStatus::PendingApproval,
        ]);
    }

    #[Test]
    public function teller_cannot_approve_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $response = $this->actingAs($teller)->post("/transactions/{$transaction->id}/approve");

        $response->assertStatus(403);
    }

    #[Test]
    public function compliance_officer_can_approve_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        // Assign compliance officer to same branch as transaction for approval authorization
        $compliance->branch_id = $counter->branch_id;
        $compliance->save();

        // Create a pending transaction — all approvals require compliance officer.
        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '5000.00',
            'rate' => '4.50',
            'amount_myr' => '22500.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::PendingApproval,
            'cdd_level' => 'Standard',
            'purpose' => 'Business',
            'source_of_funds' => 'Revenue',
            'idempotency_key' => uniqid('test_', true),
            'version' => 0,
        ]);

        // Note: No stock reservation needed for Buy transactions
        // Buy transactions add foreign currency, they don't consume it

        // Create currency position with sufficient balance
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '15000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        $response = $this->actingAs($compliance)->post("/transactions/{$transaction->id}/approve");

        // If redirect back with error, capture it
        if ($response->isRedirect() && session('error')) {
            $this->fail('Approval failed with error: '.session('error'));
        }

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::Completed,
            'approved_by' => $compliance->id,
        ]);
    }

    #[Test]
    public function compliance_officer_can_reject_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $compliance->branch_id = $counter->branch_id;
        $compliance->save();

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '5000.00',
            'rate' => '4.50',
            'amount_myr' => '22500.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::PendingApproval,
            'cdd_level' => 'Standard',
            'purpose' => 'Business',
            'source_of_funds' => 'Revenue',
            'idempotency_key' => uniqid('test_', true),
            'version' => 0,
        ]);

        $response = $this->actingAs($compliance)->post("/transactions/{$transaction->id}/reject");

        if ($response->isRedirect() && session('error')) {
            $this->fail('Rejection failed with error: '.session('error'));
        }

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::Rejected,
        ]);
    }

    #[Test]
    public function teller_cannot_reject_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $response = $this->actingAs($teller)->post("/transactions/{$transaction->id}/reject");

        $response->assertStatus(403);
    }
}
