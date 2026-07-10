<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\DTOs\PreValidationResult;
use App\Services\System\MathService;
use App\Services\Transaction\DTOs\TransactionCreationContext;

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
        return $this->creationService->create(
            $this->buildCreationContext($data, $userId, $ipAddress),
            $userId,
            $ipAddress
        );
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

    private function buildCreationContext(array $data, ?int $userId, ?string $ipAddress): TransactionCreationContext
    {
        $userId ??= auth()->id();
        $user = User::findOrFail($userId);

        $this->validationService->validateCurrency($data['currency_code']);
        $this->validationService->validateIpAddress($ipAddress ?? request()->ip());

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);
        $customer = Customer::findOrFail($data['customer_id']);

        $math = app(MathService::class);
        $amountLocal = $math->multiply((string) $data['amount_foreign'], (string) $data['rate']);

        $this->validationService->validatePepRequirements($customer, $data);

        $validationResult = $this->validationService->preValidate($customer, $amountLocal, $data['currency_code']);

        $status = $validationResult->isHoldRequired()
            ? TransactionStatus::PendingApproval
            : TransactionStatus::Completed;

        return new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $validationResult->getCDDLevel(),
            holdRequired: $validationResult->isHoldRequired(),
            status: $status,
            amountLocal: $amountLocal,
            user: $user,
        );
    }
}
