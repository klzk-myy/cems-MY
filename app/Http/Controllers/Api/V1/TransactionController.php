<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TransactionCollection;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Display a paginated list of transactions.
     */
    public function index(Request $request): TransactionCollection
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:'.TransactionType::Buy->value.','.TransactionType::Sell->value],
            'currency_code' => 'required|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
            'rate' => 'required|numeric|min:0.0001|max:999999',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'till_id' => 'required|string',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        try {
            $transaction = $this->transactionService->createTransaction(
                $validated,
                auth()->id(),
                $request->ip()
            );

            // Reload with relationships
            $transaction->load(['customer', 'user', 'approver']);

            return (new TransactionResource($transaction))
                ->additional(['success' => true, 'message' => 'Transaction created successfully.'])
                ->response($request)
                ->setStatusCode(201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a single transaction.
     */
    public function show(int $id): TransactionResource
    {
        $transaction = Transaction::with(['customer', 'user', 'approver', 'flags'])
            ->findOrFail($id);

        return (new TransactionResource($transaction))->additional(['success' => true]);
    }
}
