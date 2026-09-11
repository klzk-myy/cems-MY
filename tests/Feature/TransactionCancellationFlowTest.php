<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class TransactionCancellationFlowTest extends TestCase
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
    public function cancelled_completed_transactions_have_cancel_option(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($manager)->get("/transactions/{$transaction->id}/cancel");
        $response->assertStatus(200);
    }

    #[Test]
    public function old_transactions_cannot_be_cancelled(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $transaction->refresh();

        $response = $this->actingAs($manager)->get("/transactions/{$transaction->id}/cancel");
        $response->assertRedirect();
        $response->assertSessionHas('error', 'This transaction is outside the cancellation window.');
    }

    #[Test]
    public function only_finalized_transactions_cannot_be_cancelled(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        // Create a transaction with Finalized status (terminal state, cannot be cancelled)
        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Finalized,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Try to cancel a finalized transaction - should fail
        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/cancel", [
            'cancellation_reason' => 'Test cancellation reason with minimum length',
            'confirm_cancellation' => true,
        ]);

        $response->assertRedirect();
        // Transaction should remain Finalized
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::Finalized,
        ]);
    }

    #[Test]
    public function guest_users_cannot_access_cancellation(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.50',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Try to access cancellation without authentication
        $response = $this->post("/transactions/{$transaction->id}/cancel", [
            'cancellation_reason' => 'Test cancellation reason',
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function cancellation_reason_is_required_and_min_length(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        // Setup initial position for the sell transaction
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Try to cancel without reason
        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/cancel", [
            'confirm_understanding' => 'on',
        ]);

        $response->assertSessionHasErrors('reason');

        // Try with short reason
        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/cancel", [
            'cancellation_reason' => 'Short',
            'confirm_understanding' => 'on',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    #[Test]
    public function confirmation_checkbox_is_required(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        // Setup initial position
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Try to cancel without confirmation checkbox
        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/cancel", [
            'cancellation_reason' => 'Test cancellation reason with sufficient length',
        ]);

        $response->assertSessionHasErrors('confirm_understanding');
    }

    #[Test]
    public function cancelled_transactions_cannot_be_cancelled_again(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        // Setup initial position
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        // Create an already cancelled transaction (note: DB constraint only allows certain status values)
        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Reversed,
            'cdd_level' => 'Simplified',
            'cancelled_at' => now(),
            'cancelled_by' => $manager->id,
            'cancellation_reason' => 'Already cancelled',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Try to cancel again
        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/cancel", [
            'cancellation_reason' => 'Trying to cancel again',
            'confirm_cancellation' => true,
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function teller_transaction_can_be_cancelled_by_manager(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($manager)->get("/transactions/{$transaction->id}/cancel");
        $response->assertStatus(200);
    }

    #[Test]
    public function manager_can_cancel_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($manager)->get("/transactions/{$transaction->id}/cancel");
        $response->assertStatus(200);
    }

    #[Test]
    public function teller_cannot_cancel_other_teller_transaction(): void
    {
        $branch = Branch::factory()->create();
        $teller1 = User::factory()->for($branch)->create(['role' => UserRole::Teller]);
        $teller2 = User::factory()->for($branch)->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller1, 'USD', '1000.00');

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '500.00',
            'avg_cost_rate' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller1->id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Teller2 tries to cancel teller1's transaction
        $response = $this->actingAs($teller2)->get("/transactions/{$transaction->id}/cancel");

        // Should be forbidden
        $response->assertStatus(403);
    }
}
