<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\LargeTransactionNotification;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\KycDocumentExpiryService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\DTOs\PreValidationResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\InitialStatusResolver;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionErrorHandler;
use App\Services\Transaction\TransactionRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $mocks = []): TransactionCreationService
    {
        $cache = $mocks['cache'] ?? Mockery::mock(CacheInvalidationService::class);
        if (! isset($mocks['cache'])) {
            $cache->shouldReceive('invalidate')->with('dashboard')->zeroOrMoreTimes();
        }

        $position = $mocks['position'] ?? Mockery::mock(CurrencyPositionService::class);
        if (! isset($mocks['position'])) {
            $position->shouldReceive('getPositionWithLock')->andReturnNull();
        }

        return new TransactionCreationService(
            $mocks['idempotency'] ?? Mockery::mock(TransactionIdempotencyServiceInterface::class),
            $position,
            $mocks['accounting'] ?? Mockery::mock(TransactionAccountingService::class),
            $mocks['audit'] ?? Mockery::mock(AuditTrailHelper::class),
            $mocks['till'] ?? app(TillBalanceManager::class),
            $cache,
            $mocks['validation'] ?? app(TransactionValidationInterface::class),
            $mocks['math'] ?? app(MathService::class),
            $mocks['threshold'] ?? app(ThresholdService::class),
            $mocks['tellerAllocation'] ?? app(TellerAllocationService::class),
            $mocks['errorHandler'] ?? app(TransactionErrorHandler::class),
            // Recovery dispatch is mocked: with the sync queue a retry job would
            // re-execute booking inline and re-throw inside the test. The real
            // recovery service is covered by the scheduled sweep / job tests.
            $mocks['recoveryService'] ?? tap(Mockery::mock(TransactionRecoveryService::class), function ($mock) {
                $mock->shouldReceive('attemptRecovery')->zeroOrMoreTimes()->andReturn(false);
            }),
            $mocks['kycDocumentExpiry'] ?? app(KycDocumentExpiryService::class),
            $mocks['rateManagement'] ?? app(RateManagementServiceInterface::class),
            $mocks['statusResolver'] ?? app(InitialStatusResolver::class),
            $mocks['exchangeCalculator'] ?? app(ExchangeCalculator::class),
            $mocks['alertTriage'] ?? app(AlertTriageService::class),
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
            'quantity' => '100.00',
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
            amountMyr: $overrides['amountMyr'] ?? '450.00',
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
            'allocated_quantity' => '10000.00',
            'current_quantity' => '10000.00',
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
        $position->shouldReceive('getPositionWithLock')->twice();
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
        $this->assertEquals('100.0000', $transaction->quantity);
        $this->assertEquals('450.0000', $transaction->amount_myr);
    }

    #[Test]
    public function create_normalizes_unit_quoted_rate_to_per_unit_for_storage(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $customer = Customer::factory()->create();
        $counter = Counter::factory()->create(['status' => 'active']);
        $tillBalance = TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'IDR',
            'branch_id' => $counter->branch_id,
        ]);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $counter->branch_id,
        ]);

        // 1,000,000 IDR at RM 235 per 1,000,000 → RM 235.00, stored rate
        // normalized to per-unit 0.00023500 so quantity × rate = amount_myr.
        $data = [
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'IDR',
            'quantity' => '1000000',
            'rate' => '235',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ];

        $user = User::factory()->create(['branch_id' => $counter->branch_id]);
        $this->tellerAllocation($user, $counter, 'IDR');

        $transaction = $this->completedBuyService()->prepareAndCreate($data, $user->id);

        $this->assertSame('0.00023500', (string) $transaction->rate);
        $this->assertSame('235.0000', (string) $transaction->amount_myr);
    }

    #[Test]
    public function create_normalizes_inverse_quoted_rate_to_per_unit_for_storage(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1, 'rate_inverse' => true]);

        $customer = Customer::factory()->create();
        $counter = Counter::factory()->create(['status' => 'active']);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'IDR',
            'branch_id' => $counter->branch_id,
        ]);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $counter->branch_id,
        ]);

        // RM 1 = 4,255 IDR (inverse). Stored rate normalizes to per-unit
        // 1/4255 truncated at 8dp = 0.00023501, and amount_myr is computed
        // from that same stored rate: 4,255,000 × 0.00023501 = 999.9675.
        $data = [
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'IDR',
            'quantity' => '4255000',
            'rate' => '4255',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ];

        $user = User::factory()->create(['branch_id' => $counter->branch_id]);
        $this->tellerAllocation($user, $counter, 'IDR');

        $transaction = $this->completedBuyService()->prepareAndCreate($data, $user->id);

        $this->assertSame('0.00023501', (string) $transaction->rate);
        $this->assertSame('999.9675', (string) $transaction->amount_myr);
    }

    /**
     * @param  array<string, mixed>  $mocks
     */
    private function completedBuyService(array $mocks = []): TransactionCreationService
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->zeroOrMoreTimes();
        $position->shouldReceive('updatePosition')->zeroOrMoreTimes();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->zeroOrMoreTimes();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->zeroOrMoreTimes();

        return $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
            ...$mocks,
        ]);
    }

    #[Test]
    public function create_dispatches_large_transaction_notification_for_pending_approval_deals(): void
    {
        Notification::fake();
        config(['thresholds.cdd.large_transaction' => '1000']);

        $teller = User::factory()->teller()->create();
        $officer = User::factory()->complianceOfficer()->create();

        $this->actingAs($teller);

        $transaction = $this->completedBuyService()->create($this->context([
            'status' => TransactionStatus::PendingApproval,
            'amountMyr' => '50000.00',
        ]));

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
        Notification::assertSentTo($officer, LargeTransactionNotification::class);
        Notification::assertNotSentTo(
            User::whereKey($transaction->user_id)->first(),
            LargeTransactionNotification::class
        );
    }

    #[Test]
    public function create_skips_large_transaction_notification_for_completed_status(): void
    {
        Notification::fake();
        config(['thresholds.cdd.large_transaction' => '1000']);

        $officer = User::factory()->complianceOfficer()->create();

        $this->completedBuyService()->create($this->context([
            'amountMyr' => '50000.00',
        ]));

        // Scoped assertion: monitoring flags may legitimately produce alert
        // notifications; this test only guards the large-transaction path.
        Notification::assertNotSentTo($officer, LargeTransactionNotification::class);
    }

    #[Test]
    public function create_skips_large_transaction_notification_below_threshold(): void
    {
        Notification::fake();
        config(['thresholds.cdd.large_transaction' => '1000']);

        $officer = User::factory()->complianceOfficer()->create();

        $this->completedBuyService()->create($this->context([
            'amountMyr' => '450.00',
        ]));

        Notification::assertNotSentTo($officer, LargeTransactionNotification::class);
    }

    #[Test]
    public function create_skips_large_transaction_notification_for_failed_status(): void
    {
        Notification::fake();
        config(['thresholds.cdd.large_transaction' => '1000']);

        $officer = User::factory()->complianceOfficer()->create();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->zeroOrMoreTimes();

        // A Failed transaction reaches the notification point only via the
        // import path; the booking-failure path rethrows before this point.
        try {
            $this->completedBuyService(['audit' => $audit])->create($this->context([
                'status' => TransactionStatus::Failed,
                'amountMyr' => '50000.00',
            ]));
        } catch (\Throwable) {
            // Booking side effects may reject a Failed context; the guard is
            // what matters, not the booking outcome.
        }

        Notification::assertNotSentTo($officer, LargeTransactionNotification::class);
    }

    #[Test]
    public function create_successful_sell_transaction_creates_completed_record(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->twice()->andReturnNull();
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

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->andReturnNull();
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
        $position->shouldReceive('getPositionWithLock')->andReturnNull();
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
        $position->shouldReceive('getPositionWithLock')->twice();
        $position->shouldReceive('updatePosition')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->zeroOrMoreTimes();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'audit' => $audit,
        ]);

        try {
            $service->create($this->context(['withMyrBalance' => false]));
            $this->fail('Expected TillBalanceMissingException to propagate');
        } catch (TillBalanceMissingException $e) {
            // Booking failed after the record was persisted: the transaction
            // must now be Failed with an error record (previously the whole
            // record was rolled back).
            $transaction = Transaction::latest('id')->first();
            $this->assertNotNull($transaction);
            $this->assertEquals(TransactionStatus::Failed, $transaction->status);
            $this->assertTrue($transaction->transactionErrors()->exists());
        }
    }

    #[Test]
    public function create_marks_transaction_failed_when_booking_throws(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->andReturnNull();
        $position->shouldReceive('updatePosition')
            ->once()
            ->andThrow(new \RuntimeException('Position update failed'));

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->never();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->zeroOrMoreTimes();

        $service = $this->service([
            'idempotency' => $idempotency,
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        try {
            $service->create($this->context());
            $this->fail('Expected booking failure to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('Position update failed', $e->getMessage());
        }

        // The record must survive in Failed status with an error record so the
        // recovery sweep can retry it (previously the whole transaction rolled
        // back and the failure was unrecoverable).
        $transaction = Transaction::latest('id')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(TransactionStatus::Failed, $transaction->status);
        $this->assertNotNull($transaction->failure_reason);
        $this->assertTrue($transaction->transactionErrors()->exists());
        $this->assertSame('accounting_error', $transaction->transactionErrors()->first()->error_type->value);
    }

    #[Test]
    public function create_reserves_stock_when_pending_approval_sell(): void
    {
        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->andReturnNull();
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
        $position->shouldReceive('getPositionWithLock')->twice();
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

        $beforeBalance = $allocation->current_quantity;
        $beforeDailyUsed = $allocation->daily_used_myr;

        $service->create($this->context([
            'user' => $user,
            'allocation' => $allocation,
        ]));

        $allocation->refresh();

        $this->assertEquals(
            bcadd((string) $beforeBalance, '100.00', 4),
            (string) $allocation->current_quantity
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
        $position->shouldReceive('getPositionWithLock')->twice()->andReturnNull();
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

        $beforeBalance = $allocation->current_quantity;
        $beforeDailyUsed = $allocation->daily_used_myr;

        $service->create($this->context([
            'data' => ['type' => TransactionType::Sell->value],
            'user' => $user,
            'allocation' => $allocation,
        ]));

        $allocation->refresh();

        $this->assertEquals(
            bcsub((string) $beforeBalance, '100.00', 4),
            (string) $allocation->current_quantity
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
        $position->shouldReceive('getPositionWithLock')->twice();
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
        $position->shouldReceive('getPositionWithLock')->twice();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->withArgs(function (int $transactionId, string $action, array $metadata) {
                return $action === 'transaction_created'
                    && isset($metadata['new']['type'])
                    && isset($metadata['new']['amount_myr'])
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
        $position->shouldReceive('getPositionWithLock')->twice();
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
        $position->shouldReceive('getPositionWithLock')->twice();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $cache = Mockery::mock(CacheInvalidationService::class);
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
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);
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
        $position->shouldReceive('getPositionWithLock')->twice();
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
            'quantity' => '100.00',
            'rate' => '4.5000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $counter->code,
        ], $user->id, '127.0.0.1');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals($customer->id, $transaction->customer_id);
    }

    #[Test]
    public function sell_stock_check_scopes_pending_reservations_by_till_id(): void
    {
        // Regression: ensureStockForSell must count pending reservations by till_id,
        // not branch_id, to match the scope used by reserveStock() and
        // getAvailableBalance(). A reservation on the actual till must block a
        // subsequent Sell that exceeds the remaining position.
        $customer = Customer::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        $counter = Counter::factory()->create(['status' => 'active']);

        $tillBalance = TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $counter->branch_id,
        ]);

        // Seed position with 1000 units for the branch.
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'branch_id' => (string) $counter->branch_id,
            'quantity' => '1000.00',
            'average_cost' => '4.5000',
            'current_rate' => '4.5000',
        ]);

        // A pending reservation for the SAME till for 900 units.
        StockReservation::create([
            'transaction_id' => Transaction::factory()->create()->id,
            'currency_code' => 'USD',
            'till_id' => $counter->code,
            'quantity' => '900.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $customer->id,
        ]);

        $validation = Mockery::mock(TransactionValidationInterface::class);
        $validation->shouldReceive('validateCurrency')->zeroOrMoreTimes();
        $validation->shouldReceive('validateIpAddress')->zeroOrMoreTimes();
        $validation->shouldReceive('validateTillBalance')->zeroOrMoreTimes();
        $validation->shouldReceive('validatePepRequirements')->zeroOrMoreTimes();
        $validationResult = new PreValidationResult;
        $validationResult->setCDDLevel(CddLevel::Standard);
        $validationResult->setHoldRequired(false);
        $validation->shouldReceive('preValidate')->zeroOrMoreTimes()->andReturn($validationResult);

        $idempotency = Mockery::mock(TransactionIdempotencyServiceInterface::class);
        $idempotency->shouldReceive('findDuplicate')->andReturnNull();
        $idempotency->shouldReceive('checkRecentDuplicate')->andReturnNull();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->never();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->zeroOrMoreTimes();

        $errorHandler = Mockery::mock(TransactionErrorHandler::class);
        $errorHandler->shouldReceive('handleProcessingError')->zeroOrMoreTimes();

        $recoveryService = Mockery::mock(TransactionRecoveryService::class);
        $recoveryService->shouldReceive('attemptRecovery')->zeroOrMoreTimes();

        $service = new TransactionCreationService(
            $idempotency,
            app(CurrencyPositionService::class),
            $accounting,
            $audit,
            app(TillBalanceManager::class),
            app(CacheInvalidationService::class),
            $validation,
            app(MathService::class),
            app(ThresholdService::class),
            app(TellerAllocationService::class),
            $errorHandler,
            $recoveryService,
            app(KycDocumentExpiryService::class),
            app(RateManagementServiceInterface::class),
            app(InitialStatusResolver::class),
            app(ExchangeCalculator::class),
            app(AlertTriageService::class),
        );

        $user = User::factory()->create();

        // Trying to sell 300 (900 reserved + 300 = 1200 > 1000 position) must fail.
        $this->expectException(InsufficientStockException::class);

        $context = new TransactionCreationContext(
            data: [
                'customer_id' => $customer->id,
                'type' => TransactionType::Sell->value,
                'currency_code' => 'USD',
                'quantity' => '300.00',
                'rate' => '4.5000',
                'purpose' => 'Travel',
                'source_of_funds' => 'Savings',
                'till_id' => (string) $counter->code,
            ],
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: CddLevel::Standard,
            holdRequired: false,
            status: TransactionStatus::Completed,
            amountMyr: '1350.00',
            user: $user,
            allocation: null,
            holdReason: null,
        );

        $service->create($context, $user->id, '127.0.0.1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
