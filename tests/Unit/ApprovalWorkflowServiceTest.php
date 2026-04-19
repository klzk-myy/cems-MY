<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\ApprovalTask;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $service;

    private MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->service = new ApprovalWorkflowService($this->mathService);
    }

    public function test_requires_approval_returns_false_for_small_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '2500';

        $this->assertFalse($this->service->requiresApproval($transaction));
    }

    public function test_requires_approval_returns_true_for_threshold_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '3000';

        $this->assertTrue($this->service->requiresApproval($transaction));
    }

    public function test_requires_approval_returns_true_for_large_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '75000';

        $this->assertTrue($this->service->requiresApproval($transaction));
    }

    public function test_get_required_role_returns_null_for_small_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '2500';

        $this->assertNull($this->service->getRequiredRole($transaction));
    }

    public function test_get_required_role_returns_manager_for_threshold_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '3000';

        $this->assertEquals(UserRole::Manager, $this->service->getRequiredRole($transaction));
    }

    public function test_get_required_role_returns_manager_for_large_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '100000';

        $this->assertEquals(UserRole::Manager, $this->service->getRequiredRole($transaction));
    }

    public function test_get_threshold_amount_returns_zero_for_small_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '2500';

        $this->assertEquals('0.0000', $this->service->getThresholdAmount($transaction));
    }

    public function test_get_threshold_amount_returns_auto_approve_threshold_for_threshold_amount(): void
    {
        $transaction = new Transaction;
        $transaction->amount_local = '3000';

        $threshold = $this->service->getThresholdAmount($transaction);
        $this->assertEquals('3000', $threshold);
    }

    public function test_auto_approve_if_eligible_returns_true_for_small_amount(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->amount_local = '2500';
        $transaction->status = TransactionStatus::Completed;

        $this->assertTrue($this->service->autoApproveIfEligible($transaction));
    }

    public function test_auto_approve_if_eligible_returns_false_for_large_amount(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->amount_local = '3000';
        $transaction->status = TransactionStatus::Completed;

        $this->assertFalse($this->service->autoApproveIfEligible($transaction));
    }

    public function test_can_approve_with_manager_role(): void
    {
        $manager = new User;
        $manager->role = UserRole::Manager;

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canApprove');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $manager, 'manager');

        $this->assertTrue($result);
    }

    public function test_can_approve_with_admin_role(): void
    {
        $admin = new User;
        $admin->role = UserRole::Admin;

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canApprove');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $admin, 'admin');

        $this->assertTrue($result);
    }

    public function test_can_approve_fails_with_teller_role(): void
    {
        $teller = new User;
        $teller->role = UserRole::Teller;

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canApprove');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $teller, 'manager');

        $this->assertFalse($result);
    }

    public function test_approve_fails_for_non_actionable_task(): void
    {
        $approver = new User;
        $approver->id = 1;
        $approver->role = UserRole::Manager;

        $task = new ApprovalTask;
        $task->id = 1;
        $task->status = ApprovalTask::STATUS_APPROVED;
        $task->required_role = 'manager';

        $result = $this->service->approve($task, $approver);

        $this->assertFalse($result);
    }

    public function test_reject_fails_for_non_actionable_task(): void
    {
        $rejector = new User;
        $rejector->id = 1;
        $rejector->role = UserRole::Manager;

        $task = new ApprovalTask;
        $task->id = 1;
        $task->status = ApprovalTask::STATUS_APPROVED;
        $task->required_role = 'manager';

        $result = $this->service->reject($task, $rejector, 'Test rejection');

        $this->assertFalse($result);
    }

    public function test_expire_task_returns_false_for_non_pending_task(): void
    {
        $task = new ApprovalTask;
        $task->status = ApprovalTask::STATUS_APPROVED;

        $result = $this->service->expireTask($task);

        $this->assertFalse($result);
    }

    public function test_get_transaction_approval_status_for_no_approval_required(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->amount_local = '2500';
        $transaction->status = TransactionStatus::Completed;

        $status = $this->service->getTransactionApprovalStatus($transaction);

        $this->assertFalse($status['requires_approval']);
        $this->assertNull($status['required_role']);
        $this->assertEquals('0.0000', $status['threshold_amount']);
        $this->assertFalse($status['has_pending_task']);
    }

    public function test_get_transaction_approval_status_for_approval_required(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->amount_local = '3000';
        $transaction->status = TransactionStatus::PendingApproval;

        $status = $this->service->getTransactionApprovalStatus($transaction);

        $this->assertTrue($status['requires_approval']);
        $this->assertEquals('manager', $status['required_role']);
        $this->assertEquals('3000', $status['threshold_amount']);
    }

    public function test_sync_transaction_status_with_approval_task_returns_false_when_no_task(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->status = TransactionStatus::Completed;

        $result = $this->service->syncTransactionStatusWithApprovalTask($transaction);

        $this->assertFalse($result);
    }

    public function test_check_status_consistency_returns_true_when_no_approval_needed(): void
    {
        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->amount_local = '2500';
        $transaction->status = TransactionStatus::Completed;

        $result = $this->service->checkStatusConsistency($transaction);

        $this->assertTrue($result['consistent']);
    }

    public function test_default_expiration_hours_is_24(): void
    {
        $this->assertEquals(24, ApprovalWorkflowService::DEFAULT_EXPIRATION_HOURS);
    }

    public function test_constants_are_deprecated(): void
    {
        $reflection = new \ReflectionClass(ApprovalWorkflowService::class);
        $constant = $reflection->getConstant('AUTO_APPROVE_THRESHOLD');
        $this->assertEquals('3000', $constant);

        $constant = $reflection->getConstant('MANAGER_THRESHOLD');
        $this->assertEquals('50000', $constant);
    }
}
