<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Enums\StockReservationStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $transactionService;

    protected CurrencyPositionService $positionService;

    protected MathService $mathService;

    protected User $teller;

    protected User $manager;

    protected Branch $branch;

    protected Counter $counter;

    protected Currency $currency;

    protected Customer $customer;

    protected TillBalance $tillBalance;

    protected function setUp(): void
    {
        parent::setUp();

        // Use Laravel container to resolve services with correct dependencies
        $this->transactionService = app(TransactionService::class);
        $this->positionService = app(CurrencyPositionService::class);
        $this->mathService = app(MathService::class);

        // Setup test data
        $this->setupTestData();
    }

    protected function setupTestData(): void
    {
        // Use seeded currency instead of creating
        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->branch = Branch::factory()->create([
            'code' => 'HQ-TEST',
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter',
            'code' => 'CTR-TEST',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-15',
            'risk_rating' => 'Low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);

        $this->teller = User::factory()->create([
            'username' => 'testteller',
            'email' => 'teller@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'username' => 'testmanager',
            'email' => 'manager@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create till balance (open till)
        $this->tillBalance = TillBalance::factory()->create([
            'till_id' => $this->counter->id,
            'branch_id' => $this->branch->id,
            'currency_code' => $this->currency->code,
            'date' => today(),
            'opening_balance' => '10000.00',
            'transaction_total' => '0',
            'foreign_total' => '0',
            'opened_by' => $this->teller->id,
        ]);

        // Create MYR till balance (required by updateTillBalance)
        TillBalance::factory()->create([
            'till_id' => $this->counter->id,
            'currency_code' => 'MYR',
            'opening_balance' => '100000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        // Create active teller allocation for the currency (required by TransactionService)
        TellerAllocation::factory()->create([
            'user_id' => $this->teller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => $this->currency->code,
            'allocated_amount' => '60000.0000',
            'current_balance' => '60000.0000',
            'requested_amount' => '60000.0000',
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::ACTIVE,
            'session_date' => today(),
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
            'opened_at' => now(),
        ]);
    }

    public function test_can_create_buy_transaction(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals(TransactionType::Buy, $transaction->type);
        // Amount is stored as-provided (string), calculated amount uses BCMath
        // Check that amount_local is approximately 450 (with 6 decimal precision)
        $this->assertEqualsWithDelta(450.0, (float) $transaction->amount_local, 0.01);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);
    }

    public function test_buy_transaction_with_insufficient_stock_position_is_created(): void
    {
        // For buy transactions, position doesn't need to exist beforehand
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '500.00',
            'rate' => '4.500000',
            'purpose' => 'Investment',
            'source_of_funds' => 'Savings',
            'idempotency_key' => uniqid('test_', true),
        ];

        // Should succeed even without existing position
        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
    }

    public function test_large_transaction_requires_hold(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '12000.00',
            'rate' => '4.500000',
            'purpose' => 'Property Purchase',
            'source_of_funds' => 'Property Sale',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        // Transactions >= RM 10,000 (auto_approve threshold) get PendingApproval status
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
        // Amount 12000 * 4.5 = 54000 MYR >= 50000 = Enhanced CDD (large amount trigger)
        $this->assertEquals(CddLevel::Enhanced, $transaction->cdd_level);
        $this->assertNotNull($transaction->hold_reason);
    }

    public function test_transaction_without_till_balance_throws_exception(): void
    {
        // Close the till
        $this->tillBalance->update(['closed_at' => now()]);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
        ];

        $this->expectException(TillBalanceMissingException::class);
        $this->expectExceptionMessage('Till balance not found');

        $this->transactionService->createTransaction($data, $this->teller->id);
    }

    public function test_transaction_with_invalid_ip_throws_exception(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
        ];

        $this->expectException(InvalidIpAddressException::class);
        $this->expectExceptionMessage('Invalid IP address format');

        $this->transactionService->createTransaction($data, $this->teller->id, 'invalid-ip');
    }

    public function test_transaction_amount_precision(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '99.999999',
            'rate' => '4.123456',
            'purpose' => 'Test Precision',
            'source_of_funds' => 'Test',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Verify precision is maintained
        $this->assertStringContainsString('.', $transaction->amount_local);
        $this->assertGreaterThan(0, strlen(explode('.', $transaction->amount_local)[1] ?? ''));
    }

    public function test_transaction_creates_audit_log(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Verify audit log was created
        $this->assertDatabaseHas('system_logs', [
            'action' => 'transaction_created',
            'user_id' => $this->teller->id,
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
        ]);
    }

    public function test_idempotency_key_prevents_duplicate(): void
    {
        $idempotencyKey = uniqid('test_', true);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => $idempotencyKey,
        ];

        // Create first transaction
        $transaction1 = $this->transactionService->createTransaction($data, $this->teller->id);

        // Attempt to create duplicate with same key
        $transaction2 = $this->transactionService->createTransaction($data, $this->teller->id);

        // Should return the same transaction
        $this->assertEquals($transaction1->id, $transaction2->id);
    }

    public function test_transaction_updates_till_balance(): void
    {
        $initialForeignTotal = $this->tillBalance->foreign_total;

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $this->transactionService->createTransaction($data, $this->teller->id);

        // Refresh foreign currency till balance
        $this->tillBalance->refresh();

        // Foreign currency total should be updated (Buy = USD received)
        $this->assertNotEquals($initialForeignTotal, $this->tillBalance->foreign_total);
        $this->assertEquals('100.0000', $this->tillBalance->foreign_total);

        // MYR till balance transaction_total should reflect local value paid
        $myrBalance = TillBalance::where('till_id', $this->counter->id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($myrBalance);
        $this->assertEquals('450.0000', $myrBalance->transaction_total);
    }

    public function test_foreign_currency_position_tracked_separately_for_buy_and_sell(): void
    {
        // Reset till balance to zero for clean test
        $this->tillBalance->update([
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
            'foreign_total' => '0', // legacy field still needed for compatibility
        ]);

        // Step 1: Do a BUY transaction - should add to buy_total_foreign
        $buyData = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '500.00', // Buy 500 USD from customer
            'rate' => '4.500000', // Rate 4.5
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_buy_', true),
        ];

        $this->transactionService->createTransaction($buyData, $this->teller->id);
        $this->tillBalance->refresh();

        // After BUY: buy_total_foreign should increase, sell_total_foreign unchanged
        $this->assertEquals('500.0000', $this->tillBalance->buy_total_foreign);
        $this->assertEquals('0.0000', $this->tillBalance->sell_total_foreign);

        // Step 2: Do a SELL transaction - should add to sell_total_foreign
        $sellData = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Sell->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '200.00', // Sell 200 USD to customer
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_sell_', true),
        ];

        $this->transactionService->createTransaction($sellData, $this->teller->id);
        $this->tillBalance->refresh();

        // After SELL: sell_total_foreign should increase, buy_total_foreign unchanged
        $this->assertEquals('500.0000', $this->tillBalance->buy_total_foreign);
        $this->assertEquals('200.0000', $this->tillBalance->sell_total_foreign);

        // Step 3: Verify expected balance calculation: opening + buys - sells
        // Opening balance was 0, we bought 500 and sold 200, so net = 300 USD
        $expectedBalance = $this->mathService->add('0', $this->mathService->subtract('500.0000', '200.0000'));
        $this->assertEquals('300.0000', $this->tillBalance->getExpectedBalance());
    }

    public function test_transaction_assigns_correct_cdd_level(): void
    {
        // Test Simplified CDD (< RM 3,000)
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);

        // Test Specific CDD (RM 3,000 - 10,000) per pd-00.md 14C.12.1
        $data['amount_foreign'] = '1000.00'; // 1000 * 4.5 = 4500 MYR
        $data['idempotency_key'] = uniqid('test_', true);

        $transaction2 = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Specific, $transaction2->cdd_level);

        // Test Standard CDD (>= RM 10,000) per pd-00.md 14C.12.2
        $data['amount_foreign'] = '3000.00'; // 3000 * 4.5 = 13500 MYR
        $data['idempotency_key'] = uniqid('test_', true);

        $transaction3 = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Standard, $transaction3->cdd_level);
    }

    public function test_transaction_with_pep_customer_gets_enhanced_cdd(): void
    {
        // Mark customer as PEP
        $this->customer->update(['pep_status' => true]);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'amount_foreign' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertEquals(CddLevel::Enhanced, $transaction->cdd_level);
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    public function test_get_available_balance_excludes_pending_reservations(): void
    {
        // Create a position with 1000 USD
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'balance' => '1000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create a pending reservation for 300 USD
        StockReservation::factory()->create([
            'transaction_id' => 99999, // dummy
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'amount_foreign' => '300.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $this->teller->id,
        ]);

        $available = $this->positionService->getAvailableBalance('USD', 'TEST-TILL');

        $this->assertEquals('700.000000', $available);
    }

    public function test_reservation_consumed_on_transaction_approval(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);
        $counter = Counter::factory()->create();

        // Create till balances
        TillBalance::factory()->create([
            'till_id' => (string) $counter->id,
            'currency_code' => 'USD',
            'opening_balance' => '0',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => (string) $counter->id,
            'currency_code' => 'MYR',
            'opening_balance' => '100000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        // Create position for sell - must be large enough that available balance
        // (balance - pending reservations) >= sell amount at approval time
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => (string) $counter->id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create transaction that will go to PendingApproval
        // 2500 USD * 4.50 = 11250 MYR >= RM 10,000 auto_approve threshold
        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '2500.00',
            'rate' => '4.50',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'till_id' => (string) $counter->id,
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);

        // Verify reservation was created
        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(StockReservationStatus::Pending, $reservation->status);

        // Approve the transaction
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $result = $this->transactionService->approveTransaction($transaction, $manager->id);

        $this->assertTrue($result['success']);

        // Verify reservation was consumed
        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Consumed, $reservation->status);
    }

    public function test_approval_fails_if_stock_no_longer_available(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => false]);

        // Position has 2000 USD
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'balance' => '2000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create till balance
        TillBalance::factory()->create([
            'till_id' => 'TEST-TILL',
            'currency_code' => 'USD',
            'opening_balance' => '0',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => 'TEST-TILL',
            'currency_code' => 'MYR',
            'opening_balance' => '100000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        // Create a PendingApproval transaction for 1200 USD (reservation created)
        // 1200 USD * 10.5 = 12600 MYR >= RM 10,000 auto_approve threshold
        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '1200.00',
            'rate' => '10.5',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'till_id' => 'TEST-TILL',
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Manually reduce position to 100 (simulating another transaction consuming stock)
        $position->update(['balance' => '100.00']);

        // Approval should now fail
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $result = $this->transactionService->approveTransaction($transaction, $manager->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient stock', $result['message']);
    }

    public function test_myr_till_balance_updated_on_buy_transaction(): void
    {
        $customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'pep_status' => false,
        ]);

        $tillId = (string) $this->counter->id;

        // Create USD and MYR till balances
        TillBalance::factory()->create([
            'till_id' => $tillId,
            'currency_code' => 'USD',
            'opening_balance' => '0',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $tillId,
            'currency_code' => 'MYR',
            'opening_balance' => '10000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        // Create USD position
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => $tillId,
            'balance' => '1000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'till_id' => $tillId,
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertEquals(TransactionStatus::Completed, $transaction->status);

        // Verify MYR balance was increased (paid out for foreign currency purchase)
        $myrBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', 'MYR')
            ->first();

        // Paid 450 MYR for 100 USD (450 = 100 * 4.50)
        $this->assertEquals('450.00', $myrBalance->transaction_total);
    }

    public function test_myr_till_balance_updated_on_sell_transaction(): void
    {
        $customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'pep_status' => false,
        ]);

        $tillId = (string) $this->counter->id;

        // Create USD position and MYR till balance
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => $tillId,
            'balance' => '1000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        TillBalance::factory()->create([
            'till_id' => $tillId,
            'currency_code' => 'USD',
            'opening_balance' => '0',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $tillId,
            'currency_code' => 'MYR',
            'opening_balance' => '10000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'till_id' => $tillId,
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Verify MYR balance was increased (received MYR from foreign currency sale)
        $myrBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', 'MYR')
            ->first();

        // Received 450 MYR for 100 USD (450 = 100 * 4.50)
        $this->assertEquals('450.00', $myrBalance->transaction_total);
    }
}
