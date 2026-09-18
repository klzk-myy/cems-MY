<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transaction\ApproveCancellationAction;
use App\Actions\Transaction\RejectCancellationAction;
use App\Actions\Transaction\RequestCancellationAction;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiCancelTransactionRequest;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class TransactionCancellationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RequestCancellationAction $requestAction,
        protected ApproveCancellationAction $approveAction,
        protected RejectCancellationAction $rejectAction,
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

        if (! $result->ok) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->successResponse(
            ['transaction' => new TransactionResource($transaction->fresh())],
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

        if (! $result->ok) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->successResponse(
            ['transaction' => new TransactionResource($transaction->fresh())],
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

        if (! $result->ok) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->successResponse([
            'transaction' => new TransactionResource($transaction->fresh()),
            'previous_status' => $result->context['previous_status'] ?? null,
        ], $result->message);
    }
}
