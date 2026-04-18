<?php

namespace App\Listeners;

use App\Events\TransactionCancelled;
use App\Models\ApprovalTask;
use App\Services\ApprovalWorkflowService;
use Illuminate\Support\Facades\Log;

class TransactionCancellationTaskCleanup
{
    public function __construct(
        protected ApprovalWorkflowService $approvalWorkflowService
    ) {}

    public function handle(TransactionCancelled $event): void
    {
        $transaction = $event->transaction;

        // Find pending ApprovalTask for this transaction
        $task = ApprovalTask::where('transaction_id', $transaction->id)
            ->where('status', ApprovalTask::STATUS_PENDING)
            ->first();

        if (! $task) {
            return;
        }

        $correlationId = sprintf(
            'txn_cancel_%d_task_%d_%s',
            $transaction->id,
            $task->id,
            now()->format('Ymd_His')
        );

        // Mark the task as expired
        $this->approvalWorkflowService->expireTask($task);

        Log::info('TransactionCancellationTaskCleanup: Orphaned approval task expired', [
            'correlation_id' => $correlationId,
            'transaction_id' => $transaction->id,
            'task_id' => $task->id,
            'task_status' => ApprovalTask::STATUS_EXPIRED,
            'was_pending_approval' => $transaction->status->isPendingApproval(),
            'cancellation_reason' => $event->reason,
            'cancelled_by' => $event->cancelledBy,
        ]);

        // If transaction was PendingApproval when cancelled, this indicates potential issue - alert compliance
        if ($transaction->status->isPendingApproval()) {
            Log::warning('TransactionCancellationTaskCleanup: Transaction cancelled while pending approval - potential compliance issue', [
                'correlation_id' => $correlationId,
                'transaction_id' => $transaction->id,
                'task_id' => $task->id,
                'cancellation_reason' => $event->reason,
                'cancelled_by' => $event->cancelledBy,
            ]);
        }
    }
}
