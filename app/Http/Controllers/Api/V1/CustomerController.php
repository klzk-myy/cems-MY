<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * NOTE: Authorization gap — branch-level access control is not enforced here.
 * Customers do not have a direct branch_id field. Branch affinity is indirect via
 * their transactions. Adding branch-scoping here would require joining through
 * transactions or adding customer->branch_id, both of which need stakeholder approval.
 */
class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerService $customerService,
        protected AuditService $auditService,
    ) {}

    public function index(CustomerIndexRequest $request): CustomerCollection
    {
        $query = Customer::query();

        if ($branchScope = $request->get('_branch_scope')) {
            $query->whereHas('transactions', function ($q) use ($branchScope) {
                $q->where('branch_id', $branchScope);
            })->orWhere('created_by_branch_id', $branchScope);
        }

        if ($request->has('search') && ! empty($request->search)) {
            $searchTerm = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->where('full_name', 'like', '%'.$searchTerm.'%');
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
     * Initial risk_rating is always 'Low' - automated risk scoring module determines actual risk.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        try {
            $result = $this->customerService->createCustomerAction($validated, auth()->id());

            return $this->resourceResponse(
                new CustomerResource($result->customer->load(['documents', 'transactions'])),
                'Customer created successfully.',
                201
            );
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

        $stats = Transaction::query()
            ->selectRaw('COUNT(*) as total_transactions, SUM(amount_local) as total_volume, AVG(amount_local) as avg_transaction')
            ->where('customer_id', $id)
            ->first();

        $transactionStats = [
            'total_transactions' => $stats->total_transactions ?? 0,
            'total_volume' => $stats->total_volume ?? '0',
            'avg_transaction' => $stats->avg_transaction ?? 0,
            'last_transaction' => $customer->last_transaction_at,
        ];

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
            $result = $this->customerService->updateCustomerAction($customer, $validated, auth()->id());

            return $this->resourceResponse(
                new CustomerResource($result->customer->fresh()),
                'Customer updated successfully.'
            );
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
            return $this->errorResponse('Cannot delete customer with existing transactions.', [], 400);
        }

        $customerName = $customer->full_name;
        $customerId = $customer->id;

        $customer->delete();

        // Log customer deletion with AuditService (hash-chained for compliance)
        $this->auditService->logCustomer('customer_deleted', $customerId, [
            'old' => ['full_name' => $customerName],
        ]);

        return $this->successResponse(null, 'Customer deleted successfully.');
    }

    /**
     * Get customer transaction history.
     */
    public function customerHistory(int $id): TransactionCollection
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        $transactions = $customer->transactions()
            ->when(! auth()->user()->isAdmin(), function ($query) {
                $query->where('branch_id', auth()->user()->branch_id);
            })
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

        $validated = $request->validated();

        $file = $request->file('document');
        $path = $file->store('kyc/'.$customer->id, 'local');

        $document = $customer->documents()->create([
            'document_type' => $request->document_type,
            'file_path' => $path,
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->auditService->logWithSeverity('kyc_document_uploaded', [
            'entity_type' => 'Customer',
            'entity_id' => $customer->id,
            'new_values' => ['document_type' => $request->document_type],
        ]);

        return $this->successResponse(['document_id' => $document->id], 'Document uploaded successfully.');
    }

    /**
     * Search customers with sanctions screening for transaction form.
     * Teller enters customer name or ID, system searches and screens against sanctions.
     */
    public function searchForTransaction(SearchCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->customerService->searchCustomers($validated['query']);

        return $this->successResponse($results, 'Search completed.', 200, [
            'query' => $validated['query'],
            'count' => count($results),
        ]);
    }
}
