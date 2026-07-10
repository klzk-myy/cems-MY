<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\SelfApprovalException;
use App\Models\Transaction;

class TransactionApprovalService
{
    public function __construct(
        protected TransactionService $transactionService,
    ) {}

    public function validateApprovalEligibility(Transaction $transaction, int $approverId): void
    {
        if (! $transaction->status->isPending()) {
            throw new \InvalidArgumentException(
                'Transaction is not pending approval. Current status: '.$transaction->status->label()
            );
        }

        if ($transaction->user_id === $approverId) {
            throw new SelfApprovalException;
        }
    }

    public function approve(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
    {
        return $this->transactionService->approveTransaction(
            $transaction,
            $approverId,
            $ipAddress
        );
    }
}
