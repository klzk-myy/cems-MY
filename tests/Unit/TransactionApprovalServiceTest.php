<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Exceptions\Domain\SelfApprovalException;
use App\Models\Transaction;
use App\Services\TransactionApprovalService;
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction is not pending approval');

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

        $this->expectNotToPerformAssertions();

        $this->approvalService->validateApprovalEligibility($transaction, 2);
    }

    #[Test]
    public function validate_approval_eligibility_accepts_pending_approval_status(): void
    {
        $transaction = Transaction::factory()->make([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $this->expectNotToPerformAssertions();

        $this->approvalService->validateApprovalEligibility($transaction, 99);
    }
}
