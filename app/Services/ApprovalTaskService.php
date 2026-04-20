<?php

namespace App\Services;

use App\Models\ApprovalTask;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Approval Task Service
 *
 * Handles all approval task-related business logic including:
 * - Task status checks
 * - Task actionability validation
 * - Task assignment and approval
 * - Task expiry management
 *
 * This service removes business logic from the ApprovalTask model,
 * ensuring proper MVC separation of concerns.
 */
class ApprovalTaskService
{
    /**
     * Check if a task is pending.
     *
     * @param  ApprovalTask  $task  Task to check
     * @return bool True if task is pending
     */
    public function isPending(ApprovalTask $task): bool
    {
        return $task->status === ApprovalTask::STATUS_PENDING;
    }

    /**
     * Check if a task has been approved.
     *
     * @param  ApprovalTask  $task  Task to check
     * @return bool True if task is approved
     */
    public function isApproved(ApprovalTask $task): bool
    {
        return $task->status === ApprovalTask::STATUS_APPROVED;
    }

    /**
     * Check if a task has been rejected.
     *
     * @param  ApprovalTask  $task  Task to check
     * @return bool True if task is rejected
     */
    public function isRejected(ApprovalTask $task): bool
    {
        return $task->status === ApprovalTask::STATUS_REJECTED;
    }

    /**
     * Check if a task has expired.
     *
     * @param  ApprovalTask  $task  Task to check
     * @return bool True if task is expired
     */
    public function isExpired(ApprovalTask $task): bool
    {
        return $task->status === ApprovalTask::STATUS_EXPIRED;
    }

    /**
     * Check if a task is still actionable (pending and not expired).
     *
     * @param  ApprovalTask  $task  Task to check
     * @return bool True if task is actionable
     */
    public function isActionable(ApprovalTask $task): bool
    {
        if (! $this->isPending($task)) {
            return false;
        }

        if ($task->expires_at && $task->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get pending tasks for a specific user based on their role.
     *
     * Only returns tasks that the user CAN actually approve based on role hierarchy:
     * - Admins can see supervisor, manager, and admin tasks
     * - Managers can see supervisor and manager tasks
     * - Tellers and Compliance Officers cannot approve anything (empty result)
     *
     * @param  User  $user  User to get tasks for
     * @return Collection Collection of pending tasks
     */
    public function getPendingForUser(User $user): Collection
    {
        $query = ApprovalTask::where('status', ApprovalTask::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        // Determine which required roles the user can approve based on their role
        // Admin can approve admin and manager tasks
        // Manager can approve manager tasks only
        // Tellers and Compliance Officers cannot approve anything
        $requiredRoles = match (true) {
            $user->role->isAdmin() => ['admin', 'manager'],
            $user->role->isManager() => ['manager'],
            default => [],
        };

        if (empty($requiredRoles)) {
            return new Collection;
        }

        return $query->whereIn('required_role', $requiredRoles)
            ->with(['transaction', 'approver'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get all pending tasks.
     *
     * @return Collection Collection of pending tasks
     */
    public function getPendingTasks(): Collection
    {
        return ApprovalTask::where('status', ApprovalTask::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['transaction', 'approver'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get expired tasks.
     *
     * @return Collection Collection of expired tasks
     */
    public function getExpiredTasks(): Collection
    {
        return ApprovalTask::where('status', ApprovalTask::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->with(['transaction', 'approver'])
            ->get();
    }

    /**
     * Get tasks by status.
     *
     * @param  string  $status  Status to filter by
     * @return Collection Collection of tasks
     */
    public function getTasksByStatus(string $status): Collection
    {
        return ApprovalTask::where('status', $status)
            ->with(['transaction', 'approver'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get tasks for a specific transaction.
     *
     * @param  int  $transactionId  Transaction ID
     * @return Collection Collection of tasks
     */
    public function getTasksForTransaction(int $transactionId): Collection
    {
        return ApprovalTask::where('transaction_id', $transactionId)
            ->with(['approver'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get tasks assigned to a specific user.
     *
     * @param  int  $userId  User ID
     * @return Collection Collection of tasks
     */
    public function getTasksForUser(int $userId): Collection
    {
        return ApprovalTask::where('approver_id', $userId)
            ->with(['transaction'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
