<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SystemLog;
use App\Services\AuditService;
use App\Services\EncryptionService;
use App\Services\RiskRatingService;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * CustomerController
 *
 * Handles customer onboarding and management operations.
 * Provides CRUD operations for customer data with KYC document management.
 */
class CustomerController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EncryptionService $encryptionService,
        protected UnifiedSanctionScreeningService $sanctionService,
        protected RiskRatingService $riskRatingService
    ) {}

    /**
     * Display a paginated listing of all customers.
     *
     * @return View
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search by name - escape special LIKE characters to prevent regex DoS
        if ($request->has('search') && ! empty($request->search)) {
            $search = addcslashes($request->search, '%_');
            $query->where('full_name', 'like', "%{$search}%");
        }

        // Note: ID number search on encrypted field is not supported
        // Encrypted fields cannot be searched via SQL LIKE

        // Filter by risk rating
        if ($request->has('risk_rating') && ! empty($request->risk_rating)) {
            $query->where('risk_rating', $request->risk_rating);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        // Filter by PEP status
        if ($request->has('pep_status')) {
            $query->where('pep_status', $request->pep_status === '1');
        }

        // Filter by nationality
        if ($request->has('nationality') && ! empty($request->nationality)) {
            $query->where('nationality', $request->nationality);
        }

        // Order by - validate sort columns to prevent SQL injection
        $allowedSortColumns = ['created_at', 'updated_at', 'full_name', 'risk_rating', 'is_active', 'pep_status', 'nationality'];
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        // Whitelist validation - only allow safe column names
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        if (! in_array(strtolower($sortDir), ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        $query->withCount(['documents', 'transactions']);

        $customers = $query->paginate(20)->withQueryString();

        // Get filter options
        $riskRatings = ['Low', 'Medium', 'High'];
        $nationalities = Customer::distinct()->pluck('nationality')->sort()->toArray();

        return view('customers.index', compact(
            'customers',
            'riskRatings',
            'nationalities'
        ));
    }

    /**
     * Show the form for creating a new customer.
     *
     * @return View
     */
    public function create()
    {
        $idTypes = [
            'MyKad' => 'MyKad (Malaysian IC)',
            'Passport' => 'Passport',
            'Others' => 'Other ID',
        ];

        $riskRatings = ['Low', 'Medium', 'High'];

        // Common nationalities
        $nationalities = [
            'Malaysian',
            'Singaporean',
            'Indonesian',
            'Thai',
            'Filipino',
            'Vietnamese',
            'Chinese',
            'Indian',
            'Bangladeshi',
            'Pakistani',
            'Other',
        ];

        return view('customers.create', compact(
            'idTypes',
            'riskRatings',
            'nationalities'
        ));
    }

    /**
     * Store a newly created customer in the database.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => ['required', 'in:MyKad,Passport,Others'],
            'id_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->id_type === 'MyKad') {
                        $this->validateMyKadFormat($value, $fail);
                    }
                },
            ],
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pep_status' => 'sometimes|boolean',
            'risk_rating' => ['required', 'in:Low,Medium,High'],
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Encrypt sensitive fields
            $encryptedIdNumber = $this->encryptionService->encrypt($validated['id_number']);
            $encryptedAddress = ! empty($validated['address'])
                ? $this->encryptionService->encrypt($validated['address'])
                : null;
            $encryptedPhone = ! empty($validated['phone'])
                ? $this->encryptionService->encrypt($validated['phone'])
                : null;
            $encryptedEmployerAddress = ! empty($validated['employer_address'])
                ? $this->encryptionService->encrypt($validated['employer_address'])
                : null;

            // Create customer
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
                'employer_address' => $encryptedEmployerAddress,
                'is_active' => true,
            ]);

            // Screen against sanctions list
            $sanctionMatches = $this->sanctionService->screenName($validated['full_name']);
            $hasSanctionHit = ! empty($sanctionMatches);

            // Update sanction status, risk rating, AND deactivate if hit found
            if ($hasSanctionHit) {
                $customer->update([
                    'risk_rating' => 'High',
                    'sanction_hit' => true,
                    'is_active' => false, // Require Manager/Compliance approval to activate
                ]);
                // Log sanction hit
                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'customer_sanction_hit',
                    'severity' => 'WARNING',
                    'entity_type' => 'Customer',
                    'entity_id' => $customer->id,
                    'new_values' => [
                        'customer_name' => $customer->full_name,
                        'sanction_matches' => $sanctionMatches,
                    ],
                    'ip_address' => $request->ip(),
                ]);
            }

            // Initial risk assessment using RiskRatingService
            $riskAssessment = $this->riskRatingService->assessCustomer($customer, auth()->id());

            // Log customer creation with AuditService (hash-chained)
            $this->auditService->logCustomer('customer_created', $customer->id, [
                'new' => [
                    'full_name' => $customer->full_name,
                    'id_type' => $customer->id_type,
                    'nationality' => $customer->nationality,
                    'risk_rating' => $customer->risk_rating,
                    'pep_status' => $customer->pep_status,
                    'sanction_hit' => $hasSanctionHit,
                ],
            ]);

            DB::commit();

            $message = "Customer {$customer->full_name} created successfully.";
            if ($hasSanctionHit) {
                $message .= ' WARNING: Sanction match(es) found - customer flagged as High Risk.';
            }

            return redirect()->route('customers.show', $customer)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Customer creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_creation_failed',
                'severity' => 'ERROR',
                'new_values' => ['error' => $e->getMessage()],
                'ip_address' => $request->ip(),
            ]);

            return back()->with('error', 'Failed to create customer: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified customer's profile with transaction history.
     *
     * @return View
     */
    public function show(Customer $customer)
    {
        $customer->load(['documents', 'transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        // Calculate transaction stats
        $transactionStats = [
            'total_transactions' => $customer->transactions()->count(),
            'total_volume' => $customer->transactions()->sum('amount_local'),
            'avg_transaction' => $customer->transactions()->avg('amount_local') ?? 0,
            'last_transaction' => $customer->last_transaction_at,
        ];

        // Get document status
        $documentStatus = [
            'total' => $customer->documents()->count(),
            'verified' => $customer->documents()->verified()->count(),
            'pending' => $customer->documents()->unverified()->count(),
            'expired' => $customer->documents()->expired()->count(),
        ];

        return view('customers.show', compact(
            'customer',
            'transactionStats',
            'documentStatus'
        ));
    }

    /**
     * Show the form for editing the specified customer.
     *
     * @return View
     */
    public function edit(Customer $customer)
    {
        $idTypes = [
            'MyKad' => 'MyKad (Malaysian IC)',
            'Passport' => 'Passport',
            'Others' => 'Other ID',
        ];

        $riskRatings = ['Low', 'Medium', 'High'];

        $nationalities = [
            'Malaysian',
            'Singaporean',
            'Indonesian',
            'Thai',
            'Filipino',
            'Vietnamese',
            'Chinese',
            'Indian',
            'Bangladeshi',
            'Pakistani',
            'Other',
        ];

        // Decrypt ID number for display
        $decryptedIdNumber = $this->encryptionService->decrypt($customer->id_number_encrypted);

        return view('customers.edit', compact(
            'customer',
            'idTypes',
            'riskRatings',
            'nationalities',
            'decryptedIdNumber'
        ));
    }

    /**
     * Update the specified customer in the database.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => ['required', 'in:MyKad,Passport,Others'],
            'id_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->id_type === 'MyKad') {
                        $this->validateMyKadFormat($value, $fail);
                    }
                },
            ],
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pep_status' => 'sometimes|boolean',
            'risk_rating' => ['required', 'in:Low,Medium,High'],
            'occupation' => 'nullable|string|max:255',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = [
            'full_name' => $customer->full_name,
            'id_type' => $customer->id_type,
            'nationality' => $customer->nationality,
            'risk_rating' => $customer->risk_rating,
            'pep_status' => $customer->pep_status,
            'is_active' => $customer->is_active,
        ];

        DB::beginTransaction();
        try {
            // Encrypt sensitive fields
            $encryptedIdNumber = $this->encryptionService->encrypt($validated['id_number']);
            $encryptedAddress = ! empty($validated['address'])
                ? $this->encryptionService->encrypt($validated['address'])
                : null;
            $encryptedPhone = ! empty($validated['phone'])
                ? $this->encryptionService->encrypt($validated['phone'])
                : null;
            $encryptedEmployerAddress = ! empty($validated['employer_address'])
                ? $this->encryptionService->encrypt($validated['employer_address'])
                : null;

            // Update customer
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
                'employer_address' => $encryptedEmployerAddress,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Re-screen sanctions if name changed
            if ($oldValues['full_name'] !== $validated['full_name']) {
                $sanctionMatches = $this->sanctionService->screenName($validated['full_name']);
                if (! empty($sanctionMatches)) {
                    $customer->update(['risk_rating' => 'High']);
                }
            }

            // Re-assess risk
            $this->riskRatingService->assessCustomer($customer, auth()->id());

            // Log customer update with AuditService (hash-chained)
            $this->auditService->logCustomer('customer_updated', $customer->id, [
                'old' => $oldValues,
                'new' => [
                    'full_name' => $customer->full_name,
                    'id_type' => $customer->id_type,
                    'nationality' => $customer->nationality,
                    'risk_rating' => $customer->risk_rating,
                    'pep_status' => $customer->pep_status,
                    'is_active' => $customer->is_active,
                ],
            ]);

            DB::commit();

            return redirect()->route('customers.show', $customer)
                ->with('success', "Customer {$customer->full_name} updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Customer update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
            ]);

            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_update_failed',
                'severity' => 'ERROR',
                'entity_type' => 'Customer',
                'entity_id' => $customer->id,
                'new_values' => ['error' => $e->getMessage()],
                'ip_address' => $request->ip(),
            ]);

            return back()->with('error', 'Failed to update customer: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified customer from the database.
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request, Customer $customer)
    {
        // Require manager or admin role to delete customers
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager or Admin access required to delete customers.');
        }

        // Check if customer has transactions
        if ($customer->transactions()->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', 'Cannot delete customer with existing transactions. Deactivate instead.');
        }

        // Check if customer has unverified documents
        if ($customer->documents()->whereNull('verified_at')->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', 'Cannot delete customer with pending KYC documents.');
        }

        $customerName = $customer->full_name;
        $customerId = $customer->id;

        // Soft delete or hard delete based on requirements
        // For compliance, we typically soft delete. Using regular delete for now.
        $customer->delete();

        // Log customer deletion with AuditService (hash-chained)
        $this->auditService->logCustomer('customer_deleted', $customerId, [
            'old' => ['full_name' => $customerName],
        ]);

        return redirect()->route('customers.index')
            ->with('success', "Customer {$customerName} deleted successfully.");
    }

    /**
     * Validate MyKad ID format (12 digits in format XXXXXX-XX-XXXX).
     * Validates birthdate encoded in first 6 digits (YYMMDD).
     */
    protected function validateMyKadFormat(string $value, \Closure $fail): void
    {
        if (! preg_match('/^\d{6}-\d{2}-\d{4}$/', $value)) {
            $fail('MyKad ID must be in format XXXXXX-XX-XXXX (e.g., 900123-01-2345)');

            return;
        }

        // Validate birthdate in first 6 digits (YYMMDD)
        $birthdatePart = substr($value, 0, 6);
        $year = (int) substr($birthdatePart, 0, 2);
        $month = (int) substr($birthdatePart, 2, 2);
        $day = (int) substr($birthdatePart, 4, 2);

        // Validate month (01-12)
        if ($month < 1 || $month > 12) {
            $fail('MyKad ID contains invalid month in birthdate.');

            return;
        }

        // Validate day (01-31)
        if ($day < 1 || $day > 31) {
            $fail('MyKad ID contains invalid day in birthdate.');

            return;
        }

        // Validate days per month
        $daysInMonth = [1 => 31, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31];
        // February validation (simplified - doesn't account for leap years perfectly but catches most errors)
        if ($month === 2 && $day > 29) {
            $fail('MyKad ID contains invalid day for February.');

            return;
        }
        if (isset($daysInMonth[$month]) && $day > $daysInMonth[$month]) {
            $fail("MyKad ID contains invalid day for month {$month}.");
        }
    }
}
