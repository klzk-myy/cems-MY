<?php

namespace App\Services\Transaction;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\DTOs\PreValidationResult;

class TransactionService implements TransactionServiceInterface
{
    public function __construct(
        protected TransactionValidationInterface $validationService,
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionApprovalServiceInterface $approvalService,
        protected TransactionHoldServiceInterface $holdService,
        protected TransactionIdempotencyServiceInterface $idempotencyService,
        protected TransactionStatusServiceInterface $statusService,
    ) {}

    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult
    {
        return $this->validationService->preValidate($customer, $amount, $currencyCode);
    }

    public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        return $this->creationService->prepareAndCreate($data, $userId, $ipAddress);
    }

    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        return $this->creationService->prepareAndCreate($data, $userId, $ipAddress);
    }

    public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
    {
        $result = $this->approvalService->approve($transaction, $approverId, $ipAddress);

        return [
            'success' => $result->success,
            'message' => $result->message,
            'transaction' => $result->transaction,
        ];
    }

    public function isRefundable(Transaction $transaction): bool
    {
        return $this->statusService->isRefundable($transaction);
    }

    public function isCancelled(Transaction $transaction): bool
    {
        return $this->statusService->isCancelled($transaction);
    }
}
