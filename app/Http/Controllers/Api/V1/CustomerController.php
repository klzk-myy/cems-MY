<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\SearchCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UploadDocumentRequest;
use App\Http\Requests\Api\V1\CustomerIndexRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerCollection;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\TransactionCollection;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\Customer\CustomerService;
use App\Services\System\CacheInvalidationService;
use App\Support\LikeEscaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerService $customerService,
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
    ) {}

    public function index(CustomerIndexRequest $request): JsonResource
    {
        $this->authorize('viewAny', Customer::class);

        // Customers are company-wide entities: no branch scoping. Any role
        // may list them regardless of branch assignment.
        $query = Customer::query();

        if ($request->has('search') && ! empty($request->search)) {
            $searchTerm = '%'.LikeEscaper::escape($request->search).'%';
            $query->whereRaw('full_name LIKE ? ESCAPE ?', [$searchTerm, '\\']);
        }

        if ($request->has('risk_rating') && ! empty($request->risk_rating)) {
            $query->where('risk_rating', $request->risk_rating);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        if ($request->has('pep_status')) {
            $query->where('pep_status', $request->pep_status === '1');
        }

        $perPage = $request->get('per_page', 20);
        $customers = $query->with(['documents', 'latestRiskSnapshot'])
            ->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->resourceWithSuccess(new CustomerCollection($customers), 'Customers retrieved successfully.');
    }

    /**
     * Create a new customer.
     * Initial risk_rating is always 'low' — automated risk scoring module determines actual risk.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        try {
            $result = $this->customerService->createCustomerAction($validated, (int) auth()->id());

            return $this->resourceResponse(
                new CustomerResource($result->customer->load(['documents', 'transactions'])),
                'Customer created successfully.',
                201
            );
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Customer API store failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return $this->errorResponse('Failed to create customer. Please contact support.', [], 500);
        }
    }

    /**
     * Display a specific customer.
     */
    public function show(int $id): JsonResponse|CustomerResource
    {
        $customer = Customer::with(['documents', 'transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);

        if (! $customer) {
            return $this->notFoundResponse('Customer not found.');
        }

        $this->authorize('view', $customer);

        $transactionStats = $this->customerService->getTransactionStats($customer);

        return $this->resourceWithSuccess(
            new CustomerResource($customer),
            'Customer retrieved successfully.',
            ['transaction_stats' => $transactionStats]
        );
    }

    /**
     * Update a customer.
     * Note: risk_rating is auto-determined by risk scoring engine, not manually settable.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();

        try {
            $result = $this->customerService->updateCustomerAction($customer, $validated, (int) auth()->id());

            return $this->resourceResponse(
                new CustomerResource($result->customer->fresh()),
                'Customer updated successfully.'
            );
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Customer API update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
            ]);

            return $this->errorResponse('Failed to update customer. Please contact support.', [], 500);
        }
    }

    /**
     * Delete a customer.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $this->authorize('delete', $customer);

        if ($customer->transactions()->exists()) {
            return $this->errorResponse('Cannot delete customer with existing transactions.', [], 422);
        }

        $customerName = $customer->full_name;
        $customerId = $customer->id;

        $customer->delete();

        // Flush the per-customer cache — without this getCustomer() keeps
        // serving the soft-deleted record until TTL expiry.
        $this->cacheInvalidationService->forgetCustomer($customerId);
        $this->cacheInvalidationService->invalidate('customers');

        // Log customer deletion with AuditService (hash-chained for compliance)
        $this->auditService->logCustomer('customer_deleted', $customerId, [
            'old' => ['full_name' => $customerName],
        ]);

        return $this->successResponse(null, 'Customer deleted successfully.');
    }

    /**
     * Get customer transaction history.
     */
    public function customerHistory(int $id): JsonResource
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        // Customers are company-wide: a viewable customer's history is not
        // filtered by the caller's branch.
        $transactions = $customer->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return $this->resourceWithSuccess(new TransactionCollection($transactions), 'Customer transaction history retrieved successfully.');
    }

    /**
     * Upload KYC document for customer.
     */
    public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $this->authorize('update', $customer);

        $file = $request->file('document');
        $document = $this->customerService->uploadDocument(
            customer: $customer,
            file: $file,
            documentType: $request->document_type,
            uploadedBy: (int) auth()->id(),
        );

        return $this->successResponse(['document_id' => $document->id], 'Document uploaded successfully.');
    }

    /**
     * Search customers with sanctions screening for transaction form.
     * Teller enters customer name or ID, system searches and screens against sanctions.
     */
    public function searchForTransaction(SearchCustomerRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $validated = $request->validated();

        // Customers are company-wide: search is not scoped to the caller's branch.
        $results = $this->customerService->searchCustomers($validated['query'], null);

        return $this->successResponse($results, 'Search completed.', 200, [
            'query' => $validated['query'],
            'count' => count($results),
        ]);
    }
}
