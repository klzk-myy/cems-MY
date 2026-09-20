<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Str;

class TransactionPolicy
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
    ) {}

    /**
     * Determine whether the user can view any transactions.
     * Users can view transactions if they are assigned to a branch (or are admin).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can view the transaction.
     * Enforces branch isolation: non-admins can only view transactions from their own branch.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $transaction->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can create transactions.
     * Requires the create_transactions matrix permission (tellers by
     * default; admins always).
     */
    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::CreateTransactions);
    }

    /**
     * Determine whether the user can update the transaction.
     * Admins can update any transaction. Owners may edit their own
     * transactions only while the record is still awaiting approval
     * (Teller role); once approved the ledger record is frozen for its
     * creator.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $transaction->user_id === $user->id
            && $user->role->canPerform(Permission::CreateTransactions)
            && $transaction->status === TransactionStatus::PendingApproval;
    }

    /**
     * Determine whether the user can delete the transaction.
     * Only admins can delete transactions.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can request cancellation of the transaction.
     * Tellers can request cancellation of their own transactions; managers and
     * admins can request cancellation for transactions in their branch.
     */
    public function requestCancellation(User $user, Transaction $transaction): bool
    {
        if (! $user->role->canPerform(Permission::RequestCancellation)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        // Tellers remain scoped to their own transactions; other granted
        // roles act on their branch.
        if ($user->isTeller()) {
            return $transaction->user_id === $user->id;
        }

        return $transaction->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can approve cancellation of the transaction.
     * Managers, compliance officers, and admins can approve cancellation for transactions in their branch.
     * Approving the cancellation of a previously Completed transaction is a
     * reversal, which is compliance-only (admin inherits).
     */
    public function approveCancellation(User $user, Transaction $transaction): bool
    {
        if (! $user->role->canCancelAnyTransaction()) {
            return false;
        }

        if ($transaction->status === TransactionStatus::PendingCancellation
            && $this->preCancellationStatus($transaction) === TransactionStatus::Completed
            && ! $user->role->canReverseTransaction()) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $transaction->branch_id === $user->branch_id;
    }

    /**
     * Resolve the status the transaction held before PendingCancellation,
     * recorded in transition history by the cancellation request.
     */
    protected function preCancellationStatus(Transaction $transaction): ?TransactionStatus
    {
        foreach (array_reverse($transaction->transition_history ?? []) as $entry) {
            if (Str::snake((string) ($entry['to'] ?? '')) === TransactionStatus::PendingCancellation->value) {
                try {
                    return TransactionStatus::from(Str::snake((string) ($entry['previous_status'] ?? $entry['from'])));
                } catch (\ValueError) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Determine whether the user can reverse a completed transaction.
     * Reversal creates a compliance-gated refund record, so it is restricted
     * to roles holding reverse_transactions (manager, compliance, admin by
     * default), branch-scoped for non-admins, and never self-reversal.
     */
    public function reverse(User $user, Transaction $transaction): bool
    {
        if (! $user->role->canPerform(Permission::ReverseTransactions)) {
            return false;
        }

        // Segregation of duties: the requester must differ from the creator.
        if ($transaction->user_id === $user->id) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $transaction->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can complete an approved refund transaction.
     * Completing a refund releases funds, so it stays on the approval tier —
     * the same compliance/admin gate as approve(), applied to refund records
     * that have reached Approved status.
     */
    public function completeRefund(User $user, Transaction $transaction): bool
    {
        if (! $transaction->is_refund || ! $transaction->status->isApproved()) {
            return false;
        }

        return $this->approve($user, $transaction);
    }

    /**
     * Determine whether the user can reject cancellation of the transaction.
     * Managers, compliance officers, and admins can reject cancellation for transactions in their branch.
     */
    public function rejectCancellation(User $user, Transaction $transaction): bool
    {
        if (! $user->role->canPerform(Permission::ApproveCancellations)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $transaction->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can approve the transaction.
     * All transaction approvals require a compliance officer (or admin).
     * Branch-scoped for non-admins.
     * Per BNM segregation of duties, the approver must be different from the creator.
     */
    public function approve(User $user, Transaction $transaction): bool
    {
        // Prevent self-approval (BNM segregation of duties)
        if ($transaction->user_id === $user->id) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($transaction->branch_id !== $user->branch_id) {
            return false;
        }

        return $user->role->canApproveTransactions();
    }

    /**
     * Determine whether the user can clear a compliance hold on the transaction.
     * Compliance officers and admins, branch-scoped for compliance.
     */
    public function clearHold(User $user, Transaction $transaction): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role->canAccessCompliance()
            && $transaction->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can reject (decline) a pending transaction.
     * Mirrors approve(): managers and admins within their branch.
     */
    public function reject(User $user, Transaction $transaction): bool
    {
        return $this->approve($user, $transaction);
    }
}
