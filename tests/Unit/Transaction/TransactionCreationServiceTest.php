<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\DTOs\PreValidationResult;
use App\Services\System\CacheTagsService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Services\Transaction\TransactionCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $mocks = []): TransactionCreationService
    {
        $cache = $mocks['cache'] ?? Mockery::mock(CacheTagsService::class);
        if (! isset($mocks['cache'])) {
            $cache->shouldReceive('invalidate')->with('dashboard')->zeroOrMoreTimes();
        }

        return new TransactionCreationService(
            $mocks['idempotency'] ?? Mockery::mock(TransactionIdempotencyServiceInterface::class),
            $mocks['position'] ?? Mockery::mock(CurrencyPositionService::class),
            $mocks['accounting'] ?? Mockery::mock(TransactionAccountingService::class),
            $mocks['audit'] ?? Mockery::mock(AuditTrailHelper::class),
            $mocks['till'] ?? app(TillBalanceManager::class),
            $cache,
            $mocks['validation'] ?? app(TransactionValidationInterface::class),
            $mocks['math'] ?? app(MathService::class),
            $mocks['threshold'] ?? app(ThresholdService::class),
            $mocks['tellerAllocation'] ?? app(TellerAllocationService::class),
        );
    }

    private function context(array $overrides = []): TransactionCreationContext
    {
        $customer = Customer::factory()->create();
        $counter = Counter::factory()->create(['status' => 'active']);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $tillBalance = TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $counter->branch_id,
        ]);

        if ($overrides['withMyrBalance'] ?? true) {
            TillBalance::factory()->create([
                'till_id' => $counter->code,
                'currency_code' => 'MYR',
                'branch_id' => $counter->branch_id,
            ]);
        }

        $user = User::factory()->create();

        $data = [
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.5000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ];

        return new TransactionCreationContext(
            data: array_merge($data, $overrides['data'] ?? []),
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $overrides['cddLevel'] ?? CddLevel::Standard,
            holdRequired: $overrides['holdRequired'] ?? false,
            status: $overrides['status'] ?? TransactionStatus::Completed,
            amountLocal: $overrides['amountLocal'] ?? '450.00',
            user: $overrides['user'] ?? $user,
            allocation: $overrides['allocation'] ?? null,
            holdReason: $overrides['holdReason'] ?? null,
        );
    }

    private function tellerAllocation(User $user, Counter $counter, string $currencyCode = 'USD'): TellerAllocation
    {
        return TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'currency_code' => $currencyCode,
            'allocated_amount' => '10000.00',
            'current_balance' => '10000.00',
            'daily_limit_myr' => '50000.00',
            'daily_used_myr' => '0.00',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function create_successful_buy_transaction_creates_completed_record(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context());

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals(TransactionType::Buy->value, $transaction->type->value);
        $this->assertEquals('100.0000', $transaction->amount_foreign);
        $this->assertEquals('450.0000', $transaction->amount_local);
    }

    #[Test]
    public function create_successful_sell_transaction_creates_completed_record(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getAvailableBalance')->andReturn('1000.00');
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context([
            'data' => ['type' => TransactionType::Sell->value],
        ]));

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals(TransactionType::Sell->value, $transaction->type->value);
    }

    #[Test]
    public function create_with_hold_creates_pending_approval_transaction(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context([
            'status' => TransactionStatus::PendingApproval,
            'holdReason' => 'Large amount',
        ]));

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
        $this->assertEquals('Large amount', $transaction->hold_reason);
    }

    #[Test]
    public function create_returns_existing_transaction_when_idempotency_key_matches(): void
    {
        $existing = Transaction::factory()->create();
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturn($existing);

        $service = $this->service(['idempotency' => $idempotency]);

        $this->assertSame($existing->id, $service->create($this->context())->id);
    }

    #[Test]
    public function create_throws_duplicate_transaction_exception_when_recent_duplicate_detected(): void
    {
        $recent = Transaction::factory()->create();
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturn($recent);

        $service = $this->service(['idempotency' => $idempotency]);

        $this->expectException(DuplicateTransactionException::class);
        $service->create($this->context());
    }

    #[Test]
    public function create_throws_insufficient_stock_exception_when_sell_balance_low(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getAvailableBalance')->andReturn('50.00');

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
        ]);

        $this->expectException(InsufficientStockException::class);
        $service->create($this->context([
            'data' => ['type' => TransactionType::Sell->value],
        ]));
    }

    #[Test]
    public function create_throws_till_balance_missing_exception_when_myr_balance_absent(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
        ]);

        $this->expectException(TillBalanceMissingException::class);
        $service->create($this->context(['withMyrBalance' => false]));
    }

    #[Test]
    public function create_reserves_stock_when_pending_approval_sell(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getAvailableBalance')->andReturn('1000.00');
        $position->shouldReceive('reserveStock')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context([
            'data' => ['type' => TransactionType::Sell->value],
            'status' => TransactionStatus::PendingApproval,
        ]));

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function create_updates_teller_allocation_for_buy(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller->value]);
        $counter = Counter::factory()->create(['status' => 'active']);
        $allocation = $this->tellerAllocation($user, $counter);

        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $beforeBalance = $allocation->current_balance;
        $beforeDailyUsed = $allocation->daily_used_myr;

        $service->create($this->context([
            'user' => $user,
            'allocation' => $allocation,
        ]));

        $allocation->refresh();

        $this->assertEquals(
            bcadd((string) $beforeBalance, '100.00', 4),
            (string) $allocation->current_balance
        );
        $this->assertEquals(
            bcadd((string) $beforeDailyUsed, '450.00', 4),
            (string) $allocation->daily_used_myr
        );
    }

    #[Test]
    public function create_updates_teller_allocation_for_sell(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller->value]);
        $counter = Counter::factory()->create(['status' => 'active']);
        $allocation = $this->tellerAllocation($user, $counter);

        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getAvailableBalance')->andReturn('1000.00');
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $beforeBalance = $allocation->current_balance;
        $beforeDailyUsed = $allocation->daily_used_myr;

        $service->create($this->context([
            'data' => ['type' => TransactionType::Sell->value],
            'user' => $user,
            'allocation' => $allocation,
        ]));

        $allocation->refresh();

        $this->assertEquals(
            bcsub((string) $beforeBalance, '100.00', 4),
            (string) $allocation->current_balance
        );
        $this->assertEquals(
            bcadd((string) $beforeDailyUsed, '450.00', 4),
            (string) $allocation->daily_used_myr
        );
    }

    #[Test]
    public function create_creates_accounting_entries_for_simplified_standard_cdd(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context(['cddLevel' => CddLevel::Simplified]));

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);
    }

    #[Test]
    public function create_does_not_create_accounting_entries_for_enhanced_cdd_pending(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->never();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context([
            'cddLevel' => CddLevel::Enhanced,
            'status' => TransactionStatus::PendingApproval,
        ]));

        $this->assertEquals(CddLevel::Enhanced, $transaction->cdd_level);
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function create_logs_audit_with_correct_context(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->withArgs(function (int $transactionId, string $action, array $metadata) {
                return $action === 'transaction_created'
                    && isset($metadata['new']['type'])
                    && isset($metadata['new']['amount_local'])
                    && isset($metadata['new']['status']);
            });

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->create($this->context());

        $this->assertInstanceOf(Transaction::class, $transaction);
    }

    #[Test]
    public function create_dispatches_transaction_created_event_after_commit(): void
    {
        Event::fake([TransactionCreated::class]);

        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $service->create($this->context());

        Event::assertDispatched(TransactionCreated::class);
    }

    #[Test]
    public function create_invalidates_dashboard_cache_after_commit(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $cache = Mockery::mock(CacheTagsService::class);
        $cache->shouldReceive('invalidate')->once()->with('dashboard');

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
            'cache' => $cache,
        ]);

        $transaction = $service->create($this->context());

        $this->assertInstanceOf(Transaction::class, $transaction);
    }

    #[Test]
    public function prepare_and_create_builds_context_and_delegates_to_create(): void
    {
        $customer = Customer::factory()->create();
        $counter = Counter::factory()->create(['status' => 'active']);
        Currency::factory()->create(['code' => 'USD']);
        $tillBalance = TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $counter->branch_id,
        ]);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $counter->branch_id,
        ]);
        $user = User::factory()->create(['role' => UserRole::Manager->value]);

        $validationResult = new PreValidationResult;
        $validationResult->setCDDLevel(CddLevel::Standard);
        $validationResult->setHoldRequired(false);

        $validation = Mockery::mock(TransactionValidationInterface::class);
        $validation->shouldReceive('validateCurrency')->once();
        $validation->shouldReceive('validateIpAddress')->once();
        $validation->shouldReceive('validateTillBalance')->andReturn($tillBalance);
        $validation->shouldReceive('validatePepRequirements')->once();
        $validation->shouldReceive('preValidate')->andReturn($validationResult);

        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->once();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $service = $this->service([
            'validation' => $validation,
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $transaction = $service->prepareAndCreate([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.5000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ], $user->id, '127.0.0.1');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals($customer->id, $transaction->customer_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
