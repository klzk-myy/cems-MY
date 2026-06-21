<?php

namespace App\Http\Concerns;

use App\Models\Transaction;
use App\Models\User;

trait CancellableTransaction
{
    protected function canBeCancelled(Transaction $transaction): bool
    {
        $status = $transaction->status;

        if ($status->isFinalized()) {
            return false;
        }

        if ($status->isCancelled() || $status->isReversed()) {
            return false;
        }

        if ($transaction->cancelled_at !== null) {
            return false;
        }

        if ($transaction->is_refund) {
            return false;
        }

        if ($status->isCompleted()) {
            return $this->isWithinCancellationWindow($transaction);
        }

        return true;
    }

    protected function canRequestCancellation(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    protected function canApproveCancellation(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isComplianceOfficer();
    }

    abstract protected function isWithinCancellationWindow(Transaction $transaction): bool;
}
