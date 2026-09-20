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
use App\Exceptions\Domain\TransactionValidationException;
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
use App\Services\Accounting\CurrencyPositionService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
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
            'till_id' => $this->counter->code,
            'branch_id' => $this->branch->id,
            'currency_code' => $this->currency->code,
            'date' => today(),
            'opening_balance' => '10000.00',
            'transaction_total_myr' => '0',
            'total_quantity' => '0',
            'opened_by' => $this->teller->id,
        ]);

        // Create MYR till balance (required by updateTillBalance)
        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
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
            'allocated_quantity' => '60000.0000',
            'current_quantity' => '60000.0000',
            'requested_quantity' => '60000.0000',
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => today(),
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
            'opened_at' => now(),
        ]);
    }

    #[Test]
    public function can_create_buy_transaction(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
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
        // Check that amount_myr is approximately 450 (with 6 decimal precision)
        $this->assertEqualsWithDelta(450.0, (float) $transaction->amount_myr, 0.01);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);
    }

    #[Test]
    public function buy_transaction_with_insufficient_stock_position_is_created(): void
    {
        // For buy transactions, position doesn't need to exist beforehand
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '500.00',
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

    #[Test]
    public function enhanced_cdd_transaction_requires_hold(): void
    {
        // Mark customer as PEP so pre-validation returns Enhanced CDD and requires a hold.
        $this->customer->update(['pep_status' => true]);
        $this->approvePepFor($this->customer);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Property Purchase',
            'source_of_funds' => 'Property Sale',
            'source_of_wealth' => 'Property Sale',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
        $this->assertEquals(CddLevel::Enhanced, $transaction->cdd_level);
    }

    #[Test]
    public function transaction_without_till_balance_throws_exception(): void
    {
        // Close the till
        $this->tillBalance->update(['closed_at' => now()]);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
        ];

        $this->expectException(TillBalanceMissingException::class);
        $this->expectExceptionMessage('Till balance not found');

        $this->transactionService->createTransaction($data, $this->teller->id);
    }

    #[Test]
    public function transaction_with_invalid_ip_throws_exception(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
        ];

        $this->expectException(InvalidIpAddressException::class);
        $this->expectExceptionMessage('Invalid IP address format');

        $this->transactionService->createTransaction($data, $this->teller->id, 'invalid-ip');
    }

    #[Test]
    public function transaction_amount_precision(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '99.999999',
            'rate' => '4.123456',
            'purpose' => 'Test Precision',
            'source_of_funds' => 'Test',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Verify precision is maintained
        $this->assertStringContainsString('.', $transaction->amount_myr);
        $this->assertGreaterThan(0, strlen(explode('.', $transaction->amount_myr)[1] ?? ''));
    }

    #[Test]
    public function transaction_creates_audit_log(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
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

    #[Test]
    public function idempotency_key_prevents_duplicate(): void
    {
        $idempotencyKey = uniqid('test_', true);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
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

    #[Test]
    public function transaction_updates_till_balance(): void
    {
        $initialForeignTotal = $this->tillBalance->total_quantity;

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $this->transactionService->createTransaction($data, $this->teller->id);

        // Refresh foreign currency till balance
        $this->tillBalance->refresh();

        // Foreign currency total should be updated (Buy = USD received)
        $this->assertNotEquals($initialForeignTotal, $this->tillBalance->total_quantity);
        $this->assertEquals('100.0000', $this->tillBalance->total_quantity);

        // MYR till balance transaction_total_myr should reflect local value paid
        $myrBalance = TillBalance::where('till_id', $this->counter->code)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($myrBalance);
        $this->assertEquals('-450.0000', $myrBalance->transaction_total_myr);
    }

    #[Test]
    public function foreign_currency_position_tracked_separately_for_buy_and_sell(): void
    {
        // Reset till balance to zero for clean test
        $this->tillBalance->update([
            'opening_balance' => '0',
            'buy_quantity' => '0',
            'sell_quantity' => '0',
            'total_quantity' => '0', // legacy field still needed for compatibility
        ]);

        // Step 1: Do a BUY transaction - should add to buy_quantity
        $buyData = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '500.00', // Buy 500 USD from customer
            'rate' => '4.500000', // Rate 4.5
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_buy_', true),
        ];

        $this->transactionService->createTransaction($buyData, $this->teller->id);
        $this->tillBalance->refresh();

        // After BUY: buy_quantity should increase, sell_quantity unchanged
        $this->assertEquals('500.0000', $this->tillBalance->buy_quantity);
        $this->assertEquals('0.0000', $this->tillBalance->sell_quantity);

        // Step 2: Do a SELL transaction - should add to sell_quantity
        $sellData = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Sell->value,
            'currency_code' => $this->currency->code,
            'quantity' => '200.00', // Sell 200 USD to customer
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_sell_', true),
        ];

        $this->transactionService->createTransaction($sellData, $this->teller->id);
        $this->tillBalance->refresh();

        // After SELL: sell_quantity should increase, buy_quantity unchanged
        $this->assertEquals('500.0000', $this->tillBalance->buy_quantity);
        $this->assertEquals('200.0000', $this->tillBalance->sell_quantity);

        // Step 3: Verify expected balance calculation: opening + buys - sells
        // Opening balance was 0, we bought 500 and sold 200, so net = 300 USD
        $expectedBalance = $this->mathService->add('0', $this->mathService->subtract('500.0000', '200.0000'));
        $this->assertEquals($expectedBalance, $this->tillBalance->getExpectedBalance());
    }

    #[Test]
    public function transaction_assigns_correct_cdd_level(): void
    {
        // Test Simplified CDD (< RM 3,000)
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);

        // Test Specific CDD (RM 3,000 - 10,000) per pd-00.md 14C.12.1
        $data['quantity'] = '1000.00'; // 1000 * 4.5 = 4500 MYR
        $data['idempotency_key'] = uniqid('test_', true);

        $transaction2 = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Specific, $transaction2->cdd_level);

        // Test Standard CDD (>= RM 10,000) per pd-00.md 14C.12.2
        $data['quantity'] = '3000.00'; // 3000 * 4.5 = 13500 MYR
        $data['idempotency_key'] = uniqid('test_', true);

        $transaction3 = $this->transactionService->createTransaction($data, $this->teller->id);
        $this->assertEquals(CddLevel::Standard, $transaction3->cdd_level);
    }

    #[Test]
    public function transaction_with_pep_customer_gets_enhanced_cdd(): void
    {
        // Mark customer as PEP
        $this->customer->update(['pep_status' => true]);
        $this->approvePepFor($this->customer);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Family Inheritance', // Required for PEPs per pd-00.md 14C.13.1(c)
            'idempotency_key' => uniqid('test_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertEquals(CddLevel::Enhanced, $transaction->cdd_level);
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function get_available_balance_excludes_pending_reservations(): void
    {
        // Create a position with 1000 USD (positions key on currency + branch)
        $branch = Branch::factory()->create();
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $branch->id,
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Create a pending reservation for 300 USD on the same branch —
        // reservations are summed branch-wide, regardless of till.
        StockReservation::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'branch_id' => $branch->id,
            'quantity' => '300.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $this->teller->id,
        ]);

        $available = $this->positionService->getAvailableBalance('USD', (string) $branch->id);

        $this->assertEquals('700.000000', $available);
    }

    #[Test]
    public function reservation_consumed_on_transaction_approval(): void
    {
        // Use a PEP customer so the transaction is held for approval, which is required
        // to test reservation consumption during approval.
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => true]);
        $this->approvePepFor($customer);
        // The till must sit in the teller's branch — validateTillBalance
        // scopes counters to the actor's branch.
        $counter = Counter::factory()->create(['branch_id' => $this->branch->id]);

        // Create till balances — a Sell pays FCY out of the drawer, so the
        // USD till must physically hold the stock being sold.
        TillBalance::factory()->create([
            'till_id' => (string) $counter->code,
            'branch_id' => $counter->branch_id,
            'currency_code' => 'USD',
            'opening_balance' => '5000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => (string) $counter->code,
            'branch_id' => $counter->branch_id,
            'currency_code' => 'MYR',
            'opening_balance' => '100000.00',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        // Create position for sell - must be large enough that available balance
        // (quantity - pending reservations) >= sell amount at approval time
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '5000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Create transaction that will go to PendingApproval
        // 2500 USD * 4.50 = 11250 MYR >= RM 10,000 auto_approve threshold
        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'quantity' => '2500.00',
            'rate' => '4.50',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'source_of_wealth' => 'employment',
            'till_id' => (string) $counter->code,
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);

        // Verify reservation was created
        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(StockReservationStatus::Pending, $reservation->status);

        // Approve the transaction (compliance-only approval; clear hold first)
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        app(TransactionApprovalService::class)
            ->clearHold($transaction->fresh(), $compliance->id);
        $result = $this->transactionService->approveTransaction($transaction->fresh(), $compliance->id);

        $this->assertTrue($result['success'], $result['message'] ?? '');

        // Verify reservation was consumed
        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Consumed, $reservation->status);
    }

    #[Test]
    public function approval_fails_if_stock_no_longer_available(): void
    {
        // Use a PEP customer so the transaction is held for approval.
        $customer = Customer::factory()->create(['risk_rating' => 'Low', 'pep_status' => true]);
        $this->approvePepFor($customer);
        // The till must sit in the teller's branch — validateTillBalance
        // scopes counters to the actor's branch.
        $branch = $this->branch;
        $counter = Counter::factory()->create([
            'code' => 'BR'.$branch->id.'X',
            'branch_id' => $branch->id,
        ]);

        // Position has 2000 USD
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $branch->id,
            'quantity' => '2000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Create till balance
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'opening_balance' => '0',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
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
            'quantity' => '1200.00',
            'rate' => '10.5',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'source_of_wealth' => 'employment',
            'till_id' => $counter->code,
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        // Manually reduce position to 100 (simulating another transaction consuming stock)
        $position->update(['quantity' => '100.00']);

        // Approval should now fail (compliance-only approval; clear hold first)
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        app(TransactionApprovalService::class)
            ->clearHold($transaction->fresh(), $compliance->id);
        $result = $this->transactionService->approveTransaction($transaction->fresh(), $compliance->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient stock', $result['message']);
    }

    #[Test]
    public function myr_till_balance_updated_on_buy_transaction(): void
    {
        $customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'pep_status' => false,
        ]);

        $tillId = (string) $this->counter->code;

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
            'branch_id' => (string) $this->branch->id,
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        $data = [
            'customer_id' => $customer->id,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'quantity' => '100.00',
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

        // Paid 450 MYR for 100 USD (450 = 100 * 4.50); transaction_total_myr tracks outflow as negative
        $this->assertEquals('-450.0000', $myrBalance->transaction_total_myr);
    }

    #[Test]
    public function myr_till_balance_updated_on_sell_transaction(): void
    {
        $customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'pep_status' => false,
        ]);

        $tillId = (string) $this->counter->code;

        // Create USD position and MYR till balance
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $this->branch->id,
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
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
            'quantity' => '100.00',
            'rate' => '4.50',
            'purpose' => 'Test',
            'source_of_funds' => 'salary',
            'till_id' => $tillId,
        ];

        $this->transactionService->createTransaction($data, $this->teller->id);

        // Verify MYR balance was increased (received MYR from foreign currency sale)
        $myrBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', 'MYR')
            ->first();

        // Received 450 MYR for 100 USD (450 = 100 * 4.50)
        $this->assertEquals('450.0000', $myrBalance->transaction_total_myr);
    }

    #[Test]
    public function pep_transaction_requires_both_source_of_funds_and_source_of_wealth(): void
    {
        // Mark customer as PEP
        $this->customer->update(['pep_status' => true]);
        $this->approvePepFor($this->customer);

        // Attempt without source_of_wealth - should fail
        $dataWithoutWealth = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            // Missing source_of_wealth
            'idempotency_key' => uniqid('test_pep_', true),
        ];

        $this->expectException(TransactionValidationException::class);
        $this->expectExceptionMessage('Source of wealth is required for PEP customers');

        $this->transactionService->createTransaction($dataWithoutWealth, $this->teller->id);
    }

    #[Test]
    public function pep_transaction_succeeds_with_both_source_of_funds_and_source_of_wealth(): void
    {
        // Mark customer as PEP
        $this->customer->update(['pep_status' => true]);
        $this->approvePepFor($this->customer);

        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Investment Portfolio',
            'idempotency_key' => uniqid('test_pep_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('Investment Portfolio', $transaction->source_of_wealth);
        $this->assertEquals('Salary', $transaction->source_of_funds);
    }

    #[Test]
    public function non_pep_transaction_requires_only_source_of_funds(): void
    {
        // Ensure customer is NOT a PEP
        $this->customer->update(['pep_status' => false, 'risk_rating' => 'Low']);

        // Should succeed with only source_of_funds (source_of_wealth not required for non-PEPs)
        $data = [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            // No source_of_wealth - should still succeed for non-PEPs
            'idempotency_key' => uniqid('test_non_pep_', true),
        ];

        $transaction = $this->transactionService->createTransaction($data, $this->teller->id);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNull($transaction->source_of_wealth);
    }
}
