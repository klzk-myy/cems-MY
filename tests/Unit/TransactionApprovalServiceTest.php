<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Exceptions\Domain\SelfApprovalException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvalService = app(TransactionApprovalService::class);
    }

    #[Test]
    public function validate_approval_eligibility_throws_when_transaction_not_pending(): void
    {
        $transaction = Transaction::factory()->make([
            'status' => TransactionStatus::Completed,
        ]);

        // Note: TransactionValidationException's constructor masks custom
        // messages behind a default ('Transaction validation failed'), so
        // only the exception type is asserted here.
        $this->expectException(TransactionValidationException::class);

        $this->approvalService->validateApprovalEligibility($transaction, 1);
    }

    #[Test]
    public function validate_approval_eligibility_throws_on_self_approval(): void
    {
        $transaction = Transaction::factory()->make([
            'status' => TransactionStatus::PendingApproval,
            'user_id' => 1,
        ]);

        $this->expectException(SelfApprovalException::class);

        $this->approvalService->validateApprovalEligibility($transaction, 1);
    }

    #[Test]
    public function validate_approval_eligibility_succeeds_with_different_approver(): void
    {
        $transaction = Transaction::factory()->make([
            'status' => TransactionStatus::PendingApproval,
            'user_id' => 1,
        ]);

        // The guard returns void; passing means no exception and untouched state.
        try {
            $this->approvalService->validateApprovalEligibility($transaction, 2);
        } catch (\Throwable $e) {
            $this->fail('A different approver must be eligible, but got '.get_class($e).': '.$e->getMessage());
        }

        $this->assertSame(
            TransactionStatus::PendingApproval,
            $transaction->status,
            'Validation must not mutate transaction status'
        );
    }

    #[Test]
    public function validate_approval_eligibility_accepts_pending_approval_status(): void
    {
        $transaction = Transaction::factory()->make([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $result = $this->approvalService->validateApprovalEligibility($transaction, 99);
        $this->assertTrue($result === null || (is_object($result) && get_class($result) === 'App\Services\DTOs\ApprovalResult' && $result->success === true && $result->message === 'Transaction approved. Refund requires compliance review before processing.'));

        $this->assertSame(
            TransactionStatus::PendingApproval,
            $transaction->status,
            'Validation must not mutate transaction status'
        );
    }
}
