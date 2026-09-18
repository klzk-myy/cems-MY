<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transaction\ApproveTransactionAction;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmTransactionApprovalRequest;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionConfirmationService;
use App\Services\Transaction\TransactionStateMachineFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApproveTransactionAction $approveAction,
        protected TransactionApprovalService $approvalService,
        protected TransactionStateMachineFactory $stateMachineFactory,
        protected TransactionConfirmationService $confirmationService
    ) {}

    /**
     * Approve a pending transaction.
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('approve', $transaction);

        $result = $this->approveAction->execute($transaction, (int) auth()->id(), $request->ip());

        if (! $result->ok) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->successResponse($result->transaction, $result->message);
    }

    /**
     * Reject a pending transaction.
     */
    public function reject(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('reject', $transaction);

        $reason = $request->input('reason', 'Rejected by approver');

        try {
            if (! $this->approvalService->reject($transaction, (int) auth()->id(), $reason)) {
                return $this->errorResponse('Transaction cannot be rejected from its current status.', [], 422);
            }

            return $this->successResponse($transaction, 'Transaction has been rejected.');
        } catch (SelfApprovalException $e) {
            return $this->domainErrorResponse($e, 'You cannot reject your own transaction. Segregation of duties requires a different approver.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse('The transaction is not eligible for rejection in its current state.', [], 422);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Rejection failed due to a system error. Please contact support.', $e);
        }
    }

    /**
     * Clear a compliance hold on a pending transaction.
     */
    public function clearHold(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('clearHold', $transaction);

        try {
            $this->approvalService->clearHold($transaction, (int) auth()->id());

            return $this->successResponse($transaction->fresh(), 'Compliance hold cleared.');
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to clear compliance hold. Please contact support.', $e);
        }
    }

    /**
     * Confirm a large transaction (parity with the web confirmation flow).
     *
     * Managers confirm or reject transactions exceeding the configured
     * threshold. Self-confirmation is prohibited for segregation of duties.
     */
    public function confirm(ConfirmTransactionApprovalRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('approve', $transaction);

        if (! $this->confirmationService->requiresConfirmation($transaction)) {
            return $this->errorResponse('This transaction does not require confirmation.', [], 422);
        }

        $confirmation = $this->confirmationService->pendingConfirmationFor($transaction);

        if (! $confirmation) {
            return $this->errorResponse('No pending confirmation found. If one expired, please request a new confirmation.', [], 422);
        }

        if ($transaction->user_id === (int) auth()->id()) {
            return $this->errorResponse('You cannot confirm your own transaction. Segregation of duties requires a different manager.', [], 422);
        }

        $result = $this->confirmationService->confirm(
            $confirmation,
            $request->validated(),
            (int) auth()->id()
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], [], 422);
        }

        return $this->successResponse($confirmation->fresh(), $result['message']);
    }
}
