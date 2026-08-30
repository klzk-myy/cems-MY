<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\ProcessTransactionRetry;
use App\Models\Counter;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionError;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\DTOs\ApprovalResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionConfirmationService;
use App\Services\Transaction\TransactionErrorHandler;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessTransactionRetryTest extends TestCase
{
    use RefreshDatabase;

    private function approvalService(array $mocks = []): TransactionApprovalService
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
            Mockery::mock(TransactionMonitoringService::class),
            $mocks['position'] ?? Mockery::mock(CurrencyPositionService::class),
            $mocks['accounting'] ?? Mockery::mock(TransactionAccountingService::class),
            $mocks['audit'] ?? Mockery::mock(AuditTrailHelper::class),
            app(TillBalanceManager::class),
            $cache,
            $auditService,
            $tellerAllocation,
            new MathService,
            app(TransactionConfirmationService::class)
        );
    }

    private function openTill(): Counter
    {
        $counter = Counter::factory()->create(['status' => 'active']);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
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

    private function failedBuyTransaction(Counter $counter): Transaction
    {
        return Transaction::factory()->create([
            'status' => TransactionStatus::Failed,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'amount_local' => '450.00',
            'rate' => '4.5000',
            'till_id' => $counter->code,
            'branch_id' => $counter->branch_id,
            'cdd_level' => CddLevel::Simplified->value,
            'version' => 0,
        ]);
    }

    #[Test]
    public function job_reexecutes_failed_transaction_to_completed(): void
    {
        $counter = $this->openTill();
        $transaction = $this->failedBuyTransaction($counter);

        TransactionError::factory()->processingError()->create([
            'transaction_id' => $transaction->id,
        ]);

        $position = Mockery::mock(CurrencyPositionService::class);
        $position->shouldReceive('updatePosition')->once();

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createImmediateAccountingEntries')->once();

        $audit = Mockery::mock(AuditTrailHelper::class);
        $audit->shouldReceive('recordTransactionSealed')->once();

        $service = $this->approvalService([
            'position' => $position,
            'accounting' => $accounting,
            'audit' => $audit,
        ]);

        $job = new ProcessTransactionRetry($transaction->fresh());
        $job->handle(
            app(TransactionErrorHandler::class),
            app(TransactionRecoveryService::class),
            $service
        );

        $transaction->refresh();

        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertFalse($transaction->is_dlq);

        $error = $transaction->transactionErrors()->first();
        $this->assertNotNull($error->resolved_at);
        $this->assertSame('Retry successful', $error->resolution_notes);
    }

    #[Test]
    public function job_skips_when_transaction_no_longer_failed(): void
    {
        $counter = $this->openTill();
        $transaction = $this->failedBuyTransaction($counter);
        $transaction->status = TransactionStatus::Completed;
        $transaction->save();

        $approvalService = Mockery::mock(TransactionApprovalService::class);
        $approvalService->shouldNotReceive('reprocessFailed');

        $job = new ProcessTransactionRetry($transaction->fresh());
        $job->handle(
            app(TransactionErrorHandler::class),
            app(TransactionRecoveryService::class),
            $approvalService
        );

        $transaction->refresh();
        $this->assertSame(TransactionStatus::Completed, $transaction->status);
    }

    #[Test]
    public function job_skips_transactions_in_dead_letter_queue(): void
    {
        $counter = $this->openTill();
        $transaction = $this->failedBuyTransaction($counter);
        $transaction->is_dlq = true;
        $transaction->save();

        $approvalService = Mockery::mock(TransactionApprovalService::class);
        $approvalService->shouldNotReceive('reprocessFailed');

        $job = new ProcessTransactionRetry($transaction->fresh());
        $job->handle(
            app(TransactionErrorHandler::class),
            app(TransactionRecoveryService::class),
            $approvalService
        );

        $transaction->refresh();
        $this->assertTrue($transaction->is_dlq);
        $this->assertSame(TransactionStatus::Failed, $transaction->status);
    }

    #[Test]
    public function job_moves_to_dlq_when_reexecution_fails(): void
    {
        $counter = $this->openTill();
        $transaction = $this->failedBuyTransaction($counter);

        // Close the till so re-execution cannot book the transaction
        TillBalance::where('till_id', $counter->code)
            ->where('currency_code', 'USD')
            ->update(['closed_at' => now()]);

        $approvalService = Mockery::mock(TransactionApprovalService::class);
        $approvalService->shouldReceive('reprocessFailed')
            ->once()
            ->andReturn(new ApprovalResult(
                success: false,
                message: 'Till has been closed. Cannot approve transaction for closed till.'
            ));

        $job = new ProcessTransactionRetry($transaction->fresh());
        $job->handle(
            app(TransactionErrorHandler::class),
            app(TransactionRecoveryService::class),
            $approvalService
        );

        $transaction->refresh();
        $this->assertTrue($transaction->is_dlq);
        $this->assertStringStartsWith('[DLQ]', $transaction->failure_reason);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
