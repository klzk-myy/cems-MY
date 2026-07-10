<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Models\AuditTrail;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\SystemLog;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\System\CacheTagsService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Mock for FlagType
class MockFlagType
{
    public function __construct(public bool $isHighPriority) {}

    public function isHighPriority(): bool
    {
        return $this->isHighPriority;
    }

    public function label(): string
    {
        return 'Test Flag';
    }
}

class TransactionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createTillBalance(array $overrides = []): TillBalance
    {
        $data = [
            'till_id' => 'MAIN',
            'branch_id' => 1,
            'currency_code' => 'MYR',
            'date' => today(),
            'foreign_total' => '10000.00',
            'buy_total_foreign' => '5000.00',
            'sell_total_foreign' => '5000.00',
            'transaction_total' => '5000.00',
            'closed_at' => null,
        ];
        $data = array_merge($data, $overrides);

        Counter::firstOrCreate(
            ['code' => $data['till_id']],
            ['branch_id' => $data['branch_id'], 'name' => 'Main Counter']
        );

        return TillBalance::factory()->create($data);
    }

    private function buildService(
        ?TransactionMonitoringService $monitoring = null,
        ?CurrencyPositionService $position = null,
        ?TransactionAccountingService $accounting = null,
        ?AuditService $audit = null,
        ?CacheTagsService $cache = null,
        ?TellerAllocationService $teller = null,
        ?MathService $math = null
    ): TransactionApprovalService {
        $monitoring ??= $this->createMockMonitoringService();
        $position ??= $this->createMockPositionService();
        $accounting ??= $this->createMockAccountingService();
        $audit ??= $this->createMockAuditService();
        $cache ??= $this->createMockCacheTagsService();
        $teller ??= $this->createMockTellerAllocationService();
        $math ??= $this->createMockMathService();

        $this->app->instance(MathService::class, $math);
        $this->app->instance(CurrencyPositionService::class, $position);
        $this->app->instance(TransactionAccountingService::class, $accounting);
        $this->app->instance(AuditService::class, $audit);
        $this->app->instance(CacheTagsService::class, $cache);
        $this->app->instance(TellerAllocationService::class, $teller);
        $this->app->instance(TransactionMonitoringService::class, $monitoring);

        $auditTrailHelper = $this->createMock(AuditTrailHelper::class);
        $auditTrailHelper->method('recordTransaction')->willReturn(AuditTrail::make());
        $auditTrailHelper->method('recordCustomer')->willReturn(AuditTrail::make());
        $this->app->instance(AuditTrailHelper::class, $auditTrailHelper);

        return new TransactionApprovalService($this->app->make(TransactionService::class));
    }

    private function createMockMonitoringService(?array $amlResult = null)
    {
        $mock = $this->createMock(TransactionMonitoringService::class);
        $mock->method('monitorTransaction')->willReturn($amlResult ?? [
            'flags_created' => 0,
            'flags' => [],
            'status' => TransactionStatus::PendingApproval,
        ]);

        return $mock;
    }

    private function createMockPositionService(?callable $getAvailableCallback = null)
    {
        $mock = $this->createMock(CurrencyPositionService::class);
        if ($getAvailableCallback) {
            $mock->method('getAvailableBalance')->willReturnCallback($getAvailableCallback);
        } else {
            $mock->method('getAvailableBalance')->willReturn('5000.00');
        }
        $mock->method('consumeStockReservation')->willReturn(StockReservation::factory()->make());
        $mock->method('updatePosition')->willReturn(CurrencyPosition::factory()->make());

        return $mock;
    }

    private function createMockAccountingService()
    {
        $mock = $this->createMock(TransactionAccountingService::class);
        $mock->method('createImmediateAccountingEntries');
        $mock->method('createDeferredAccountingEntries');

        return $mock;
    }

    private function createMockAuditService()
    {
        $mock = $this->createMock(AuditService::class);
        $mock->method('logTransaction')->willReturn(SystemLog::factory()->make());
        $mock->method('logWithSeverity')->willReturn(SystemLog::factory()->make());

        return $mock;
    }

    private function createMockCacheTagsService()
    {
        $mock = $this->createMock(CacheTagsService::class);
        $mock->method('invalidate');

        return $mock;
    }

    private function createMockTellerAllocationService(?TellerAllocation $allocation = null)
    {
        $mock = $this->createMock(TellerAllocationService::class);
        if ($allocation) {
            $mock->method('getActiveAllocation')->willReturn($allocation);
        } else {
            $mock->method('getActiveAllocation')->willReturn(null);
        }

        return $mock;
    }

    private function createMockMathService()
    {
        $mock = $this->createMock(MathService::class);
        $mock->method('compare')->willReturn(1);
        $mock->method('add')->willReturnCallback(fn ($a, $b) => bcadd($a, $b, 2));
        $mock->method('subtract')->willReturnCallback(fn ($a, $b) => bcsub($a, $b, 2));

        return $mock;
    }

    #[Test]
    public function approve_successful_transaction_returns_success_result(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'amount_foreign' => '1000.00',
            'amount_local' => '4500.00',
            'rate' => '4.5000',
            'purpose' => 'Test',
            'source_of_funds' => 'Business',
            'cdd_level' => CddLevel::Standard->value,
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $service = $this->buildService();

        $result = $service->approve($transaction, 2, '127.0.0.1');

        $this->assertIsArray($result);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals('Transaction approved and completed successfully.', $result['message']);
        $this->assertInstanceOf(Transaction::class, $result['transaction']);
        $this->assertEquals(TransactionStatus::Completed, $result['transaction']->status);
        $this->assertEquals(2, $result['transaction']->approved_by);
    }

    #[Test]
    public function approve_with_high_priority_aml_flags_returns_failure(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $flagType = new MockFlagType(true);
        $flagObject = (object) ['flag_type' => $flagType];
        $amlResult = [
            'flags_created' => 1,
            'flags' => [$flagObject],
            'status' => TransactionStatus::PendingApproval,
        ];
        $monitoringMock = $this->createMockMonitoringService($amlResult);

        $auditMock = $this->createMockAuditService();
        $auditMock->expects($this->once())
            ->method('logWithSeverity')
            ->with(
                $this->equalTo('transaction_approval_blocked'),
                $this->arrayHasKey('new_values'),
                $this->equalTo('WARNING')
            );

        $service = $this->buildService(
            monitoring: $monitoringMock,
            audit: $auditMock
        );

        $result = $service->approve($transaction, 2);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('High-priority AML flags', $result['message']);
        $this->assertNull($result['transaction'] ?? null);
    }

    #[Test]
    public function approve_transaction_not_pending_throws_invalid_argument_exception(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::Completed->value,
            'version' => 1,
        ]);

        $service = $this->buildService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not pending approval');
        $service->approve($transaction, 2);
    }

    #[Test]
    public function approve_with_stale_data_returns_failure(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $service = $this->buildService();

        // Simulate concurrent modification: increment version in DB directly
        Transaction::where('id', $transaction->id)->update(['version' => 2]);

        // Use the original (now stale) transaction object
        $result = $service->approve($transaction, 2);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('modified by another user', $result['message']);
    }

    #[Test]
    public function approve_records_transition_history_and_increments_version(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
            'transition_history' => [],
        ]);

        $service = $this->buildService();
        $result = $service->approve($transaction, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['transaction']->version);

        $history = $result['transaction']->transition_history;
        $this->assertCount(1, $history);
        $this->assertEquals('PendingApproval', $history[0]['from']);
        $this->assertEquals('Completed', $history[0]['to']);
        $this->assertEquals(2, $history[0]['user_id']);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    #[Test]
    public function approve_sets_approver_and_timestamp(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $service = $this->buildService();
        $result = $service->approve($transaction, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['transaction']->approved_by);
        $this->assertNotNull($result['transaction']->approved_at);
    }

    #[Test]
    public function approve_validates_ip_address(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $service = $this->buildService();

        $this->expectException(InvalidIpAddressException::class);
        $this->expectExceptionMessage('Invalid IP address');
        $service->approve($transaction, 2, 'invalid-ip');
    }

    #[Test]
    public function approve_with_null_ip_uses_request_ip(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $this->app['request']->server->set('REMOTE_ADDR', '127.0.0.1');
        $service = $this->buildService();

        $result = $service->approve($transaction, 2, null);
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function approve_with_enhanced_cdd_creates_deferred_accounting_entries(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'cdd_level' => CddLevel::Enhanced->value,
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $accountingMock = $this->createMock(TransactionAccountingService::class);
        $accountingMock->expects($this->once())
            ->method('createDeferredAccountingEntries')
            ->with($transaction->id);

        $service = $this->buildService(accounting: $accountingMock);

        $result = $service->approve($transaction, 2);
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function approve_with_simple_cdd_creates_immediate_accounting_entries(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'cdd_level' => CddLevel::Standard->value,
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $accountingMock = $this->createMock(TransactionAccountingService::class);
        $accountingMock->expects($this->once())
            ->method('createImmediateAccountingEntries')
            ->with($this->callback(fn ($passedTx) => $passedTx->id === $transaction->id));

        $service = $this->buildService(accounting: $accountingMock);

        $result = $service->approve($transaction, 2);
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function approve_sell_with_insufficient_stock_returns_failure(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '5000.00',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        // Create a CurrencyPosition so the existence check passes
        CurrencyPosition::factory()->create([
            'currency_code' => 'MYR',
            'branch_id' => 1,
            'quantity' => '10000.00',
        ]);

        // Position mock with low available balance
        $positionMock = $this->createMockPositionService();
        $positionMock->method('getAvailableBalance')->willReturn('100.00');

        // Custom MathService mock to force "available < amount_foreign" condition
        $mathMock = $this->createMock(MathService::class);
        $mathMock->method('compare')->willReturn(-1);
        $mathMock->method('add')->willReturnCallback(fn ($a, $b) => bcadd($a, $b, 2));
        $mathMock->method('subtract')->willReturnCallback(fn ($a, $b) => bcsub($a, $b, 2));

        $service = $this->buildService(position: $positionMock, math: $mathMock);

        $result = $service->approve($transaction, 2);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient stock', $result['message']);
    }

    #[Test]
    public function approve_sell_with_expired_reservation_returns_failure(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '1000.00',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        // Create a CurrencyPosition so the existence check passes
        CurrencyPosition::factory()->create([
            'currency_code' => 'MYR',
            'branch_id' => 1,
            'quantity' => '10000.00',
        ]);

        $positionMock = $this->createMock(CurrencyPositionService::class);
        $positionMock->method('getAvailableBalance')->willReturn('5000.00');
        $positionMock->method('consumeStockReservation')->willReturn(null);

        $service = $this->buildService(position: $positionMock);

        $result = $service->approve($transaction, 2);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('reservation expired', $result['message']);
    }

    #[Test]
    public function approve_dispatches_event_and_invalidates_cache_after_commit(): void
    {
        $customer = $this->createCustomer();
        $user = $this->createUser();
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'MYR',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        Event::fake();

        $cacheMock = $this->createMockCacheTagsService();
        $cacheMock->expects($this->once())
            ->method('invalidate')
            ->with('dashboard');

        $service = $this->buildService(cache: $cacheMock);

        $result = $service->approve($transaction, 2);
        $this->assertTrue($result['success']);

        Event::assertDispatched(TransactionApproved::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    }

    #[Test]
    public function approve_teller_transaction_updates_allocation(): void
    {
        $customer = $this->createCustomer();
        $user = User::factory()->create(['role' => 'teller']);
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'type' => TransactionType::Buy->value,
            'amount_foreign' => '1000.00',
            'amount_local' => '4500.00',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        $allocation = TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'currency_code' => 'MYR',
            'allocated_amount' => '10000.00',
            'current_balance' => '10000.00',
            'daily_used_myr' => '0.00',
        ]);

        $tellerMock = $this->createMock(TellerAllocationService::class);
        $tellerMock->method('getActiveAllocation')->willReturn($allocation);

        $service = $this->buildService(teller: $tellerMock);

        $result = $service->approve($transaction, 2);
        $this->assertTrue($result['success']);

        $allocation->refresh();
        $this->assertEquals('11000.0000', $allocation->current_balance);
        $this->assertEquals('4500.0000', $allocation->daily_used_myr);
    }

    #[Test]
    public function approve_sell_teller_transaction_deducts_allocation(): void
    {
        $customer = $this->createCustomer();
        $user = User::factory()->create(['role' => 'teller']);
        $tillBalance = $this->createTillBalance(['branch_id' => 1]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'branch_id' => 1,
            'till_id' => $tillBalance->till_id,
            'currency_code' => 'MYR',
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '1000.00',
            'amount_local' => '4500.00',
            'status' => TransactionStatus::PendingApproval->value,
            'version' => 1,
        ]);

        // Create a CurrencyPosition for Sell validation
        CurrencyPosition::factory()->create([
            'currency_code' => 'MYR',
            'branch_id' => 1,
            'quantity' => '10000.00',
        ]);

        $allocation = TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'currency_code' => 'MYR',
            'allocated_amount' => '10000.00',
            'current_balance' => '10000.00',
            'daily_used_myr' => '0.00',
        ]);

        $tellerMock = $this->createMock(TellerAllocationService::class);
        $tellerMock->method('getActiveAllocation')->willReturn($allocation);

        $service = $this->buildService(teller: $tellerMock);

        $result = $service->approve($transaction, 2);
        $this->assertTrue($result['success']);

        $allocation->refresh();
        $this->assertEquals('9000.0000', $allocation->current_balance);
        $this->assertEquals('4500.0000', $allocation->daily_used_myr);
    }
}
