<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\DomainException;
use App\Http\Concerns\DeterminesTransactionStatus;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\StoreTransactionRequest;
use App\Http\Requests\Api\V1\TransactionIndexRequest;
use App\Http\Resources\Api\V1\TransactionCollection;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\MathService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    use ApiResponse;
    use DeterminesTransactionStatus;

    public function __construct(
        protected TransactionValidationInterface $validationService,
        protected TransactionCreationServiceInterface $creationService,
        protected MathService $mathService
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

        return $this->resourceWithSuccess(new TransactionCollection($transactions), 'Transactions retrieved successfully.');
    }

    /**
     * Store a new transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $ipAddress = $request->ip();

        try {
            $this->validationService->validateCurrency($validated['currency_code']);
            $this->validationService->validateIpAddress($ipAddress);

            $tillBalance = $this->validationService->validateTillBalance($validated['till_id'], $validated['currency_code']);

            $customer = Customer::findOrFail($validated['customer_id']);
            $amountLocal = $this->mathService->multiply((string) $validated['amount_foreign'], (string) $validated['rate']);

            $this->validationService->validatePepRequirements($customer, $validated);

            $validationResult = $this->validationService->preValidate($customer, $amountLocal, $validated['currency_code']);

            if ($validationResult->isBlocked()) {
                $block = $validationResult->getBlocks()[0];

                return $this->errorResponse($block['message'], ['reason' => $block['type']], 403);
            }

            $user = User::findOrFail(auth()->id());
            $allocation = $this->determineTellerAllocation($user, $validated, $amountLocal);
            $status = $this->determineInitialStatus($amountLocal, $validationResult->isHoldRequired());

            $context = new TransactionCreationContext(
                data: $validated,
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

            $transaction = $this->creationService->create($context, $user->id, $ipAddress);

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

        return $this->resourceWithSuccess(new TransactionResource($transaction), 'Transaction retrieved successfully.');
    }
}
