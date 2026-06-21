<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerNoteRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Services\AuditService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        protected CustomerService $customerService,
        protected AuditService $auditService,
    ) {}

    /**
     * Get exchange rates for transaction form.
     */
    public function getExchangeRates(): JsonResponse
    {
        $rates = ExchangeRate::all()
            ->mapWithKeys(fn ($r) => [$r->currency_code => [
                'buy' => (float) $r->rate_buy,
                'sell' => (float) $r->rate_sell,
            ]]);

        return response()->json([
            'success' => true,
            'rates' => $rates,
        ]);
    }

    /**
     * Display a paginated listing of all customers.
     */
    public function index(Request $request): View
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

        // Use withCount for aggregated data only - avoid N+1 by not eager loading full relationships
        $query->with(['documents', 'latestRiskSnapshot'])
            ->withCount(['documents']);

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
     */
    public function create(): View
    {
        $idTypes = [
            'MyKad' => 'MyKad (Malaysian IC)',
            'Passport' => 'Passport',
            'Others' => 'Other ID',
        ];

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
            'nationalities'
        ));
    }

    /**
     * Store a newly created customer in the database.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        try {
            $customer = $this->customerService->createCustomer($validated, auth()->id());

            $message = "Customer {$customer->full_name} created successfully.";
            if ($customer->sanction_hit) {
                $message .= ' WARNING: Sanction match(es) found - customer flagged as High Risk.';
            }

            return redirect()->route('customers.show', $customer)
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Customer store failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to create customer. Please contact support.')
                ->withInput();
        }
    }

    /**
     * Display the specified customer's profile with transaction history.
     */
    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['documents', 'transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        $customer->loadCount(['transactions', 'documents']);
        $customer->loadSum('transactions', 'amount_local');
        $customer->loadAvg('transactions', 'amount_local');

        $notes = $customer->notes()
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate transaction stats
        $transactionStats = [
            'total_transactions' => $customer->transactions_count,
            'total_volume' => $customer->transactions_sum_amount_local,
            'avg_transaction' => $customer->transactions_avg_amount_local ?? 0,
            'last_transaction' => $customer->last_transaction_at,
        ];

        // Get document status from the already-loaded collection
        $documentStatus = [
            'total' => $customer->documents_count,
            'verified' => $customer->documents->filter->isVerified()->count(),
            'pending' => $customer->documents->whereNull('verified_by')->whereNull('verified_at')->count(),
            'expired' => $customer->documents->whereNotNull('expiry_date')->where('expiry_date', '<', now())->count(),
        ];

        return view('customers.show', compact(
            'customer',
            'transactionStats',
            'documentStatus',
            'notes'
        ));
    }

    /**
     * Store a new note for the specified customer.
     */
    public function storeNote(StoreCustomerNoteRequest $request, Customer $customer): RedirectResponse
    {
        $customer->notes()->create([
            'note' => $request->validated('note'),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

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
        $decryptedIdNumber = $this->customerService->decryptIdNumber($customer);

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
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();

        try {
            $customer = $this->customerService->updateCustomer($customer, $validated, auth()->id());

            return redirect()->route('customers.show', $customer)
                ->with('success', "Customer {$customer->full_name} updated successfully.");

        } catch (\Exception $e) {
            Log::error('Customer update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to update customer. Please contact support.')
                ->withInput();
        }
    }

    /**
     * Remove the specified customer from the database.
     */
    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

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
