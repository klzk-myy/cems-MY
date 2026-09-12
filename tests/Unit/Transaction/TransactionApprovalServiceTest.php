<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\StockReservationStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\SelfApprovalException;
use App\Exceptions\Domain\TransactionConfirmationRequiredException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\StockReservation;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\TransactionOutcomeNotification;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionConfirmationService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $mocks = []): TransactionApprovalService
    {
        $cache = $mocks['cache'] ?? Mockery::mock(CacheInvalidationService::class);
        if (! isset($mocks['cache'])) {
            $cache->shouldReceive('invalidate')->with('dashboard')->zeroOrMoreTimes();
        }

        $auditService = $mocks['auditService'] ?? Mockery::mock(AuditService::class);

        $tellerAllocation = $mocks['tellerAllocation'] ?? Mockery::mock(TellerAllocationService::class);
        if (! isset($mocks['tellerAllocation'])) {
            $tellerAllocation->shouldReceive('applyTransactionAllocation')->andReturnNull();
        }

        return new TransactionApprovalService(
            $mocks['monitoring'] ?? Mockery::mock(TransactionMonitoringService::class),
            $mocks['position'] ?? Mockery::mock(CurrencyPositionService::class),
            $mocks['accounting'] ?? Mockery::mock(TransactionAccountingService::class),
            $mocks['audit'] ?? Mockery::mock(AuditTrailHelper::class),
            $mocks['till'] ?? app(TillBalanceManager::class),
            $cache,
            $auditService,
            $tellerAllocation,
            new MathService,
            $mocks['confirmation'] ?? app(TransactionConfirmationService::class),
            app(ThresholdService::class)
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
        $audit->shouldReceive('recordTransactionSealed')
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
        $audit->shouldReceive('recordTransactionSealed')->once();

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
    public function approve_throws_when_confirmation_required_but_not_confirmed(): void
    {
        config(['thresholds.reporting.str' => '50000']);
        // 75k is above the large-transaction threshold — only compliance may approve.
        $approver = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, ['amount_local' => '75000.00']);

        $monitoring = Mockery::mock(TransactionMonitoringService::class);
        $monitoring->shouldReceive('monitorTransaction')->never();

        $this->expectException(TransactionConfirmationRequiredException::class);

        $this->service([
            'monitoring' => $monitoring,
        ])->approve($transaction, $approver->id);
    }

    #[Test]
    public function approve_succeeds_when_confirmation_confirmed(): void
    {
        config(['thresholds.reporting.str' => '50000']);
        $approver = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, ['amount_local' => '75000.00']);

        TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Confirmed->value,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success, $result->message);
        $this->assertSame(TransactionStatus::Completed, $result->transaction->status);
    }

    #[Test]
    public function validate_eligibility_throws_when_transaction_not_pending(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
        ]);
        $approver = User::factory()->create();

        $this->expectException(TransactionValidationException::class);

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
            ->with($transaction->currency_code, (string) $transaction->branch_id, (string) $transaction->till_id)
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
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
            'tellerAllocation' => app(TellerAllocationService::class),
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
        $audit->shouldReceive('recordTransactionSealed')
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

                return $action === 'transaction_approved' && $severity === 'CRITICAL';
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

        $cache = Mockery::mock(CacheInvalidationService::class);
        $cache->shouldReceive('invalidate')->once()->with('dashboard');

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

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
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
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

    #[Test]
    public function approve_records_status_transition_history(): void
    {
        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);

        $history = $result->transaction->fresh()->transition_history;
        $last = array_pop($history);

        $this->assertSame(TransactionStatus::PendingApproval->value, $last['from']);
        $this->assertSame(TransactionStatus::Completed->value, $last['to']);
        $this->assertSame('Transaction approved and completed by manager', $last['reason']);
        $this->assertSame($approver->id, $last['user_id']);
        $this->assertArrayHasKey('timestamp', $last);
    }

    #[Test]
    public function reprocess_failed_buy_transaction_books_to_completed(): void
    {
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'status' => TransactionStatus::Failed,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')
            ->once()
            ->withArgs(function (int $transactionId, string $action, array $metadata) {
                return $action === 'transaction_reexecuted'
                    && $metadata['old']['status'] === TransactionStatus::Failed->value
                    && $metadata['new']['status'] === TransactionStatus::Completed->value;
            });

        $result = $this->service([
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->reprocessFailed($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(TransactionStatus::Completed, $result->transaction->status);

        $history = $result->transaction->fresh()->transition_history;
        $last = array_pop($history);
        $this->assertSame(TransactionStatus::Failed->value, $last['from']);
        $this->assertSame(TransactionStatus::Completed->value, $last['to']);
        $this->assertSame('Automated retry after failure', $last['reason']);
    }

    #[Test]
    public function reprocess_failed_sell_transaction_with_already_consumed_reservation(): void
    {
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'status' => TransactionStatus::Failed,
            'type' => TransactionType::Sell->value,
        ]);

        // The original (partially successful) attempt already consumed the stock
        StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => $counter->code,
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Consumed,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('getPositionWithLock')
            ->with($transaction->currency_code, (string) $transaction->branch_id)
            ->andReturn(CurrencyPosition::factory()->make());
        $position->shouldReceive('getAvailableBalance')
            ->with($transaction->currency_code, (string) $transaction->branch_id, (string) $transaction->till_id)
            ->andReturn('1000.00');
        $position->shouldReceive('consumeStockReservation')
            ->with($transaction->id)
            ->andReturnNull();
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->reprocessFailed($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(TransactionStatus::Completed, $result->transaction->status);
    }

    #[Test]
    public function reprocess_failed_returns_failure_when_transaction_not_failed(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
        ]);

        $result = $this->service()->reprocessFailed($transaction);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not in Failed status', $result->message);
    }

    #[Test]
    public function reprocess_failed_returns_failure_when_transaction_in_dlq(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Failed,
            'is_dlq' => true,
        ]);

        $result = $this->service()->reprocessFailed($transaction);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('dead letter queue', $result->message);
    }

    #[Test]
    public function reprocess_failed_returns_failure_when_till_closed(): void
    {
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, [
            'status' => TransactionStatus::Failed,
        ]);

        TillBalance::where('till_id', $counter->code)
            ->where('currency_code', 'USD')
            ->update(['closed_at' => now()]);

        $result = $this->service()->reprocessFailed($transaction);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Till has been closed', $result->message);
    }

    #[Test]
    public function approve_does_not_send_duplicate_outcome_notification(): void
    {
        // Approval notifications are owned by the queued TransactionApprovedListener
        // (fired via the TransactionApproved event); the outcome notification is
        // reserved for rejections so the teller never receives two notifications.
        Notification::fake();

        $approver = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

        $result = $this->service([
            'monitoring' => $this->monitoringMock(),
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ])->approve($transaction, $approver->id);

        $this->assertTrue($result->success);

        Notification::assertSentToTimes($approver, TransactionOutcomeNotification::class, 0);
    }

    #[Test]
    public function reject_notifies_teller_with_outcome_and_reason(): void
    {
        Notification::fake();

        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $rejector = User::factory()->create(['role' => UserRole::Manager]);
        $counter = $this->openTill();
        $transaction = $this->pendingTransaction($counter, ['user_id' => $teller->id]);

        $rejected = $this->service([
            'monitoring' => $this->monitoringMock(),
        ])->reject($transaction, $rejector->id, 'Duplicate entry');

        $this->assertTrue($rejected);
        $transaction->refresh();
        $this->assertSame(TransactionStatus::Rejected, $transaction->status);

        Notification::assertSentTo(
            $teller,
            TransactionOutcomeNotification::class,
            function (TransactionOutcomeNotification $notification) use ($transaction, $rejector) {
                return $notification->outcome === 'rejected'
                    && $notification->reason === 'Duplicate entry'
                    && $notification->actorName === $rejector->username
                    && $notification->transaction->id === $transaction->id;
            }
        );

        Notification::assertSentToTimes($rejector, TransactionOutcomeNotification::class, 0);
    }

    #[Test]
    public function reject_throws_for_non_pending_transaction_without_notifying(): void
    {
        Notification::fake();

        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $rejector = User::factory()->create(['role' => UserRole::Manager]);
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
            'user_id' => $teller->id,
        ]);

        $this->expectException(TransactionValidationException::class);

        try {
            $this->service()->reject($transaction, $rejector->id, 'Too late');
        } finally {
            Notification::assertSentToTimes($teller, TransactionOutcomeNotification::class, 0);
            Notification::assertSentToTimes($rejector, TransactionOutcomeNotification::class, 0);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
