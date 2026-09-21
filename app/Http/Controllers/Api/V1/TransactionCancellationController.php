<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transaction\ApproveCancellationAction;
use App\Actions\Transaction\RejectCancellationAction;
use App\Actions\Transaction\RequestCancellationAction;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiCancelTransactionRequest;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionCancellationService;
use Illuminate\Http\JsonResponse;

class TransactionCancellationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RequestCancellationAction $requestAction,
        protected ApproveCancellationAction $approveAction,
        protected RejectCancellationAction $rejectAction,
        protected TransactionCancellationService $cancellationService,
        protected TransactionApprovalService $approvalService,
    ) {}

    public function requestCancellation(ApiCancelTransactionRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('requestCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->requestAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result->success) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->resourceResponse(
            new TransactionResource($transaction->fresh()),
            $result->message
        );
    }

    public function approveCancellation(ApproveCancelRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('approveCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->approveAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason'] ?? null
        );

        if (! $result->success) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->resourceResponse(
            new TransactionResource($transaction->fresh()),
            $result->message
        );
    }

    public function rejectCancellation(RejectCancelRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('rejectCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->rejectAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result->success) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->resourceResponse(
            (new TransactionResource($transaction->fresh()))
                ->additional(['previous_status' => $result->context['previous_status'] ?? null]),
            $result->message
        );
    }

    /**
     * Reverse a completed transaction.
     *
     * Compensates positions, till balances, journal entries and teller
     * allocations on the original, then creates a refund transaction that
     * moves through compliance clearance, approval and completion.
     */
    public function requestReversal(ApiCancelTransactionRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('reverse', $transaction);

        try {
            $reversed = $this->cancellationService->requestReversal(
                $transaction,
                auth()->user(),
                $request->validated('reason')
            );
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        }

        if (! $reversed) {
            return $this->errorResponse(
                'This transaction cannot be reversed. Only completed transactions within the reversal window are eligible.'
            );
        }

        $transaction->refresh();
        $refund = $transaction->refundTransaction;

        return $this->successResponse([
            'transaction' => new TransactionResource($transaction),
            'refund' => $refund ? new TransactionResource($refund) : null,
        ], 'Transaction reversed. Refund transaction created pending compliance approval.');
    }

    /**
     * Complete an approved refund transaction (Approved -> Completed).
     *
     * The compensating legs were booked at reversal time; this records the
     * compliance-approved physical settlement on the refund record.
     */
    public function completeRefund(int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('completeRefund', $transaction);

        try {
            $result = $this->approvalService->completeRefund(
                $transaction,
                (int) auth()->id(),
                request()->ip()
            );
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        }

        if (! $result->success) {
            return $this->errorResponse($result->message);
        }

        return $this->resourceResponse(
            new TransactionResource($result->transaction ?? $transaction->fresh()),
            $result->message
        );
    }
}
