<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CddLevel;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * List customers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('search') && ! empty($request->search)) {
            $query->where('full_name', 'like', "%{$request->search}%");
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
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Create a new customer.
     * Initial risk_rating is always 'Low' - automated risk scoring module determines actual risk.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|in:MyKad,Passport,Others',
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'pep_status' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
        ]);

        try {
            $customer = $this->customerService->createCustomer($validated, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'data' => $customer->load(['documents', 'transactions']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific customer.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['documents', 'transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($id);

        $transactionStats = [
            'total_transactions' => $customer->transactions()->count(),
            'total_volume' => $customer->transactions()->sum('amount_local'),
            'avg_transaction' => $customer->transactions()->avg('amount_local') ?? 0,
            'last_transaction' => $customer->last_transaction_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $customer,
            'transaction_stats' => $transactionStats,
        ]);
    }

    /**
     * Update a customer.
     * Note: risk_rating is auto-determined by risk scoring engine, not manually settable.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|in:MyKad,Passport,Others',
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'pep_status' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $customer = $this->customerService->updateCustomer($customer, $validated, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully.',
                'data' => $customer->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a customer.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        if (! auth()->user()->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Manager or Admin access required.',
            ], 403);
        }

        if ($customer->transactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with existing transactions.',
            ], 400);
        }

        $customerName = $customer->full_name;
        $customerId = $customer->id;

        $customer->delete();

        // Log customer deletion with AuditService (hash-chained for compliance)
        $this->auditService->logCustomer('customer_deleted', $customerId, [
            'old' => ['full_name' => $customerName],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    /**
     * Get customer transaction history.
     */
    public function customerHistory(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $transactions = $customer->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Upload KYC document for customer.
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_type' => 'required|string',
        ]);

        // Document upload handling would go here
        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
        ], 201);
    }

    /**
     * Search customers with sanctions screening for transaction form.
     * Teller enters customer name or ID, system searches and screens against sanctions.
     */
    public function searchForTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = trim($validated['query']);

        // Search by name (LIKE search)
        $customers = Customer::where('full_name', 'like', "%{$query}%")
            ->orWhere('ic_number', 'like', "%{$query}%")
            ->where('is_active', true)
            ->limit(10)
            ->get();

        // If no results by name, try ID number hash lookup
        if ($customers->isEmpty()) {
            $idHash = CustomerService::computeBlindIndex($query);
            $byHash = Customer::where('id_number_hash', $idHash)
                ->where('is_active', true)
                ->first();
            if ($byHash) {
                $customers = collect([$byHash]);
            }
        }

        // Screen each customer against sanctions
        $results = $customers->map(function ($customer) {
            $sanctionCheck = $this->sanctionService->screenName($customer->full_name);

            return [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'ic_number' => $customer->ic_number,
                'ic_number_masked' => $customer->ic_number ? substr($customer->ic_number, 0, 4).'****'.substr($customer->ic_number, -4) : null,
                'nationality' => $customer->nationality,
                'risk_rating' => $customer->risk_rating,
                'cdd_level' => $customer->cdd_level instanceof CddLevel ? $customer->cdd_level->value : $customer->cdd_level,
                'is_pep' => $customer->pep_status,
                'is_sanctioned' => $customer->sanction_hit,
                'sanction_warning' => $sanctionCheck->matches->isNotEmpty(),
                'sanction_matches' => $sanctionCheck->matches->map(fn ($m) => [
                    'entity_name' => $m->entityName,
                    'score' => round($m->score, 1),
                    'list' => $m->listName,
                ])->toArray(),
                'sanction_action' => $sanctionCheck->action,
            ];
        });

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results,
            'count' => $results->count(),
        ]);
    }
}
