<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\DTOs\PreValidationResult;
use App\Services\System\MathService;
use App\Services\ThresholdService;
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
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
    ) {}

    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult
    {
        return $this->validationService->preValidate($customer, $amount, $currencyCode);
    }

    public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        return $this->prepareAndCreate($data, $userId, $ipAddress);
    }

    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $userId ??= auth()->id();
        $user = User::findOrFail($userId);
        $ipAddress ??= request()?->ip();

        $this->validationService->validateCurrency($data['currency_code']);
        $this->validationService->validateIpAddress($ipAddress);

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);
        $customer = Customer::findOrFail($data['customer_id']);

        $amountLocal = $this->mathService->multiply(
            (string) $data['amount_foreign'],
            (string) $data['rate']
        );

        $this->validationService->validatePepRequirements($customer, $data);

        $validationResult = $this->validationService->preValidate($customer, $amountLocal, $data['currency_code']);

        if ($validationResult->isBlocked()) {
            throw new TransactionBlockedException($validationResult->getBlocks()[0]['message']);
        }

        $allocation = $this->determineTellerAllocation($user, $data, $amountLocal);
        $status = $this->determineInitialStatus($amountLocal, $validationResult->isHoldRequired());

        $context = new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $validationResult->getCDDLevel(),
            holdRequired: $validationResult->isHoldRequired(),
            status: $status,
            amountLocal: $amountLocal,
            user: $user,
            allocation: $allocation,
            holdReason: $validationResult->isHoldRequired() ? 'Compliance hold' : null,
        );

        return $this->creationService->create($context, $user->id, $ipAddress);
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

    /**
     * Determine the teller allocation to attach to a new transaction.
     *
     * @param  User  $user  The authenticated user creating the transaction.
     * @param  array{type: string, currency_code: string}  $data  Validated transaction data.
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @return TellerAllocation|null The active teller allocation, or null for non-tellers.
     *
     * @throws AllocationValidationException When the active allocation cannot cover the transaction.
     */
    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?TellerAllocation
    {
        if (! $user->isTeller()) {
            return null;
        }

        $service = app(TellerAllocationService::class);

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $service->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new AllocationValidationException($result->reason);
            }

            /** @var TellerAllocation|null $allocation */
            $allocation = $result->allocation;

            return $allocation;
        }

        return $service->getActiveAllocation($user, $data['currency_code']);
    }

    /**
     * Decide whether a transaction should start as Completed or PendingApproval.
     *
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     */
    private function determineInitialStatus(string $amountLocal, bool $holdRequired): TransactionStatus
    {
        if ($holdRequired || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
