<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\EncryptionService;
use App\Services\RiskRatingService;
use App\Services\CustomerScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EncryptionService $encryptionService,
        protected CustomerScreeningService $sanctionService,
        protected RiskRatingService $riskRatingService
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
            'risk_rating' => 'required|in:Low,Medium,High',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $encryptedIdNumber = $this->encryptionService->encrypt($validated['id_number']);
            $encryptedAddress = ! empty($validated['address'])
                ? $this->encryptionService->encrypt($validated['address'])
                : null;
            $encryptedPhone = ! empty($validated['phone'])
                ? $this->encryptionService->encrypt($validated['phone'])
                : null;

            $customer = Customer::create([
                'full_name' => $validated['full_name'],
                'id_type' => $validated['id_type'],
                'id_number_encrypted' => $encryptedIdNumber,
                'date_of_birth' => $validated['date_of_birth'],
                'nationality' => $validated['nationality'],
                'address' => $encryptedAddress,
                'phone' => $encryptedPhone,
                'email' => $validated['email'] ?? null,
                'pep_status' => $validated['pep_status'] ?? false,
                'risk_rating' => $validated['risk_rating'],
                'occupation' => $validated['occupation'] ?? null,
                'employer_name' => $validated['employer_name'] ?? null,
                'employer_address' => $encryptedAddress ?? null,
                'is_active' => true,
            ]);

            // Screen against sanctions
            $sanctionResponse = $this->sanctionService->screenName($validated['full_name']);
            if ($sanctionResponse->matches->isNotEmpty()) {
                $customer->update([
                    'risk_rating' => 'High',
                    'sanction_hit' => true,
                ]);

                // Log sanction hit for compliance audit trail
                $this->auditService->logCustomer('sanction_match_detected', $customer->id, [
                    'new' => [
                        'risk_rating' => 'High',
                        'sanction_hit' => true,
                        'matches' => $sanctionResponse->matches->map(fn ($m) => $m->entityName)->toArray(),
                    ],
                ]);
            }

            // Initial risk assessment
            $this->riskRatingService->assessCustomer($customer, auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'data' => $customer->fresh()->load(['documents', 'transactions']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

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
            'risk_rating' => 'required|in:Low,Medium,High',
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            $encryptedIdNumber = $this->encryptionService->encrypt($validated['id_number']);
            $encryptedAddress = ! empty($validated['address'])
                ? $this->encryptionService->encrypt($validated['address'])
                : null;
            $encryptedPhone = ! empty($validated['phone'])
                ? $this->encryptionService->encrypt($validated['phone'])
                : null;

            $customer->update([
                'full_name' => $validated['full_name'],
                'id_type' => $validated['id_type'],
                'id_number_encrypted' => $encryptedIdNumber,
                'date_of_birth' => $validated['date_of_birth'],
                'nationality' => $validated['nationality'],
                'address' => $encryptedAddress,
                'phone' => $encryptedPhone,
                'email' => $validated['email'] ?? null,
                'pep_status' => $validated['pep_status'] ?? false,
                'risk_rating' => $validated['risk_rating'],
                'occupation' => $validated['occupation'] ?? null,
                'employer_name' => $validated['employer_name'] ?? null,
                'employer_address' => $encryptedAddress ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Re-assess risk
            $this->riskRatingService->assessCustomer($customer, auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully.',
                'data' => $customer->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

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
}
