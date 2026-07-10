<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\SelfApprovalException;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TillBalanceManager;
use App\Services\System\CacheTagsService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $mocks = []): TransactionApprovalService
    {
        $cache = $mocks['cache'] ?? Mockery::mock(CacheTagsService::class);
        if (! isset($mocks['cache'])) {
            $cache->shouldReceive('invalidate')->with('dashboard')->zeroOrMoreTimes();
        }

        return new TransactionApprovalService(
            $mocks['monitoring'] ?? Mockery::mock(TransactionMonitoringService::class),
            $mocks['position'] ?? Mockery::mock(CurrencyPositionService::class),
            $mocks['accounting'] ?? Mockery::mock(TransactionAccountingService::class),
            $mocks['audit'] ?? Mockery::mock(AuditTrailHelper::class),
            $mocks['till'] ?? app(TillBalanceManager::class),
            $cache
        );
    }

    private function openTill(string $currencyCode = 'USD'): Counter
    {
        $counter = Counter::factory()->create(['status' => 'active']);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currencyCode,
            'branch_id' => $counter->branch_id,
            'date' => today(),
            'closed_at' => null,
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $counter->branch_id,
            'date' => today(),
            'closed_at' => null,
        ]);

        return $counter;
    }

    private function pendingTransaction(Counter $counter, array $overrides = []): Transaction
    {
        $defaults = [
            'status' => TransactionStatus::PendingApproval,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.5000',
            'till_id' => $counter->code,
            'branch_id' => $counter->branch_id,
            'version' => 0,
            'cdd_level' => CddLevel::Simplified->value,
        ];

        return Transaction::factory()->create(array_merge($defaults, $overrides));
    }

    private function monitoringMock(array $flags = []): TransactionMonitoringService
    {
        $mock = Mockery::mock(TransactionMonitoringService::class);
        $mock->shouldReceive('monitorTransaction')->andReturn([
            'flags' => $flags,
            'flags_created' => count($flags),
        ]);

        return $mock;
    }

    private function highPriorityFlag(): FlaggedTransaction
    {
        return new FlaggedTransaction([
            'flag_type' => ComplianceFlagType::Structuring,
        ]);
    }

    #[Test]
    public function approve_successful_simple_cdd_creates_immediate_accounting(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();
        $accounting->shouldReceive('createDeferredAccountingEntries')->never();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->withArgs(function (int $transactionId, string $action) {
                return $transactionId > 0 && $action === 'transaction_approved';
            });

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);
        $this->assertSame(TransactionStatus::Completed, $result->transaction->status);
        $this->assertSame($approver->id, $result->transaction->approved_by);
    }

    #[Test]
    public function approve_successful_enhanced_cdd_creates_deferred_accounting(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'cdd_level' => CddLevel::Enhanced->value,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createDeferredAccountingEntries')
            ->once()
            ->with($transaction->id);
        $accounting->shouldReceive('createImmediateAccountingEntries')->never();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);
        $this->assertSame(CddLevel::Enhanced, $result->transaction->cdd_level);
        $this->assertSame(TransactionStatus::Completed, $result->transaction->status);
    }

    #[Test]
    public function approve_blocked_by_high_priority_aml_flags(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->withArgs(function (int $transactionId, string $action, array $metadata, ?User $user, string $severity) {
                return $action === 'transaction_approval_blocked'
                    && $severity === 'WARNING'
                    && $metadata['new']['reason'] === 'High-priority AML flags';
            });

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->never();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->never();

        $result = $this->service([
            'monitoring' => $this->monitoringMock([$this->highPriorityFlag()]),
            'audit' => $audit,
            'position' => $position,
            'accounting' => $accounting,
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Approval blocked', $result->message);
        $this->assertStringContainsString('Structuring Detection', $result->message);

        $transaction->refresh();
        $this->assertSame(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function approve_returns_failure_when_version_mismatch(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        // Simulate another process updating the version
        Transaction::where('id', $transaction->id)->update(['version' => 5]);

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('modified by another user', $result->message);
    }

    #[Test]
    public function validate_eligibility_throws_when_transaction_not_pending(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
        ]);
        $approver = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->validateApprovalEligibility($transaction, $approver->id);
    }

    #[Test]
    public function validate_eligibility_throws_on_self_approval(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
            'user_id' => $user->id,
        ]);

        $this->expectException(SelfApprovalException::class);

        $this->service()->validateApprovalEligibility($transaction, $user->id);
    }

    #[Test]
    public function approve_returns_failure_when_customer_deleted(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        Customer::where('id', $transaction->customer_id)->delete();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Customer has been deleted', $result->message);
    }

    #[Test]
    public function approve_returns_failure_when_till_closed(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        TillBalance::where('till_id', $counter->code)
            ->where('currency_code', 'USD')
            ->update(['closed_at' => now()]);

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Till has been closed', $result->message);
    }

    #[Test]
    public function approve_returns_failure_when_sell_position_deleted(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'type' => TransactionType::Sell->value,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')
            ->with($transaction->currency_code, (string) $transaction->branch_id)
            ->andReturnNull();
        $position->shouldReceive('updatePosition')->never();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Currency position has been deleted', $result->message);
    }

    #[Test]
    public function approve_returns_failure_when_insufficient_stock(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '500.00',
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->andReturn(CurrencyPosition::factory()->make());
        $position->shouldReceive('getAvailableBalance')
            ->with($transaction->currency_code, (string) $transaction->branch_id)
            ->andReturn('100.00');
        $position->shouldReceive('consumeStockReservation')->never();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient stock', $result->message);
    }

    #[Test]
    public function approve_returns_failure_when_stock_reservation_expired(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'type' => TransactionType::Sell->value,
            'amount_foreign' => '100.00',
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')->andReturn(CurrencyPosition::factory()->make());
        $position->shouldReceive('getAvailableBalance')->andReturn('1000.00');
        $position->shouldReceive('consumeStockReservation')
            ->with($transaction->id)
            ->andReturnNull();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
        ])->approve($transaction, $approver->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Stock reservation expired', $result->message);
    }

    #[Test]
    public function approve_updates_teller_allocation_for_buy(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();

        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'currency_code' => 'USD',
            'allocated_amount' => '10000.00',
            'current_balance' => '10000.00',
            'daily_limit_myr' => '50000.00',
            'daily_used_myr' => '0.00',
            'status' => TellerAllocationStatus::ACTIVE,
            'session_date' => today(),
        ]);

        $transaction = $this->pendingTransaction($counter, [
            'user_id' => $teller->id,
            'type' => TransactionType::Buy->value,
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);

        $allocation->refresh();
        $this->assertEquals('10100.0000', (string) $allocation->current_balance);
        $this->assertEquals('450.0000', (string) $allocation->daily_used_myr);
    }

    #[Test]
    public function approve_logs_transaction_approved_audit_with_context(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $captured = null;

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')
            ->once()
            ->withArgs(function (
                int $transactionId,
                string $action,
                array $metadata,
                ?User $user,
                string $severity,
                ?string $ip
            ) use (&$captured) {
                $captured = compact('transactionId', 'action', 'metadata', 'user', 'severity', 'ip');

                return $action === 'transaction_approved' && $severity === 'INFO';
            });

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id, '127.0.0.1');

        $this->assertNotNull($captured);
        $this->assertSame($transaction->id, $captured['transactionId']);
        $this->assertSame($approver->id, $captured['user']?->id);
        $this->assertSame('127.0.0.1', $captured['ip']);
        $this->assertSame(TransactionStatus::PendingApproval->value, $captured['metadata']['old']['status']);
        $this->assertSame(TransactionStatus::Completed->value, $captured['metadata']['new']['status']);
        $this->assertSame($approver->id, $captured['metadata']['new']['approved_by']);
    }

    #[Test]
    public function approve_invalidates_dashboard_cache_after_commit(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $cache = Mockery::mock(CacheTagsService::class);
        $cache->shouldReceive('invalidate')->once()->with('dashboard');

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
            'cache' => $cache,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function approve_dispatches_transaction_approved_event(): void
    {
        Event::fake([TransactionApproved::class]);

        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransaction')->once();

        $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        Event::assertDispatched(TransactionApproved::class, function (TransactionApproved $event) use ($transaction, $approver) {
            return $event->transaction->id === $transaction->id
                && $event->approverId === $approver->id;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
