<?php

namespace App\Services\Contracts;

use App\Models\Transaction;
use App\Services\DTOs\ApprovalResult;

interface TransactionApprovalServiceInterface
{
    /**
     * Approve a pending transaction and complete its side effects.
     *
     * @param  Transaction  $transaction  Must be PendingApproval status
     * @param  int  $approverId  User ID of manager/admin
     * @param  string|null  $ipAddress  IP address for audit logging
     * @return ApprovalResult DTO with success, message, transaction (if success)
     *
     * @throws \InvalidArgumentException If transaction is not pending
     * @throws \RuntimeException If transaction was already processed or data is stale
     */
    public function approve(Transaction $transaction, int $approverId, ?string $ipAddress = null): ApprovalResult;
}
