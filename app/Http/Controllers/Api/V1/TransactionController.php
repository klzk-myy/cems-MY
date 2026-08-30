<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Http\Concerns\BranchScopedQuery;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\StoreTransactionRequest;
use App\Http\Requests\Api\V1\TransactionIndexRequest;
use App\Http\Resources\Api\V1\TransactionCollection;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Transaction\ReceiptGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    use ApiResponse, BranchScopedQuery;

    public function __construct(
        protected TransactionCreationServiceInterface $creationService,
        protected ReceiptGenerationService $receiptService
    ) {}

    /**
     * Display a paginated list of transactions.
     */
    public function index(TransactionIndexRequest $request): TransactionCollection
    {
        $this->authorize('viewAny', Transaction::class);

        $perPage = $request->get('per_page', 20);
        $query = Transaction::with(['customer', 'user', 'branch']);

        $transactions = $this->scopeByBranch($query)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->resourceWithSuccess(new TransactionCollection($transactions), 'Transactions retrieved successfully.');
    }

    /**
     * Store a new transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validated();
        $ipAddress = $request->ip();

        try {
            $transaction = $this->creationService->prepareAndCreate($validated, (int) auth()->id(), $ipAddress);

            $transaction->load(['customer', 'user', 'approver']);

            return $this->resourceResponse(
                new TransactionResource($transaction),
                'Transaction created successfully.',
                201
            );
        } catch (TransactionBlockedException $e) {
            return $this->errorResponse('Transaction blocked due to compliance restrictions.', ['reason' => 'blocked'], 403);
        } catch (DomainException $e) {
            return $this->errorResponse('Transaction validation failed.', [], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Transaction failed. Please contact support.', $e);
        }
    }

    /**
     * Display a single transaction.
     */
    public function show(int $id): TransactionResource
    {
        $transaction = $this->scopeByBranch(Transaction::with(['customer', 'user', 'approver', 'flags']))
            ->findOrFail($id);

        $this->authorize('view', $transaction);

        return $this->resourceWithSuccess(new TransactionResource($transaction), 'Transaction retrieved successfully.');
    }

    /**
     * Stream a PDF receipt for a completed transaction.
     *
     * Mirrors the web TransactionController::receipt gating: branch-scoped
     * lookup, view policy, and completed-status requirement.
     */
    public function receipt(int $id): JsonResponse|Response
    {
        $transaction = $this->scopeByBranch(Transaction::with(['customer', 'user', 'approver']))
            ->findOrFail($id);

        $this->authorize('view', $transaction);

        if (! $transaction->status->isCompleted()) {
            return $this->errorResponse(
                'Receipts can only be generated for completed transactions.',
                [],
                422
            );
        }

        return $this->receiptService->generate($transaction);
    }
}
