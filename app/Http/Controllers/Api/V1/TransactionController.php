<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\StoreTransactionRequest;
use App\Http\Requests\Api\V1\TransactionIndexRequest;
use App\Http\Resources\Api\V1\TransactionCollection;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Display a paginated list of transactions.
     */
    public function index(TransactionIndexRequest $request): TransactionCollection
    {
        $perPage = $request->get('per_page', 20);
        $query = Transaction::with(['customer', 'user', 'branch']);

        // Branch segregation: non-admin users can only see their branch's transactions
        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return (new TransactionCollection($transactions))->additional(['success' => true]);
    }

    /**
     * Store a new transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transaction = $this->transactionService->createTransaction(
                $validated,
                auth()->id(),
                $request->ip()
            );

            // Reload with relationships
            $transaction->load(['customer', 'user', 'approver']);

            return $this->resourceResponse(
                new TransactionResource($transaction),
                'Transaction created successfully.',
                201
            );

        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);

        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), [], $e->getStatusCode());

        } catch (\Exception $e) {
            return $this->serverErrorResponse('Transaction failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * Display a single transaction.
     */
    public function show(int $id): TransactionResource
    {
        $transaction = Transaction::with(['customer', 'user', 'approver', 'flags'])
            ->findOrFail($id);

        $this->authorize('view', $transaction);

        return (new TransactionResource($transaction))->additional(['success' => true]);
    }
}
