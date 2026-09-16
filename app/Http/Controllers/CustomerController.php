<?php

namespace App\Http\Controllers;

use App\Actions\Customer\CustomerIndexAction;
use App\Enums\Permission;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Requests\CloseCustomerRequest;
use App\Http\Requests\FreezeCustomerRequest;
use App\Http\Requests\StoreCustomerNoteRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CustomerController
 *
 * Handles customer onboarding and management operations.
 * Provides CRUD operations for customer data with KYC document management.
 */
class CustomerController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected CustomerService $customerService,
        protected AuditService $auditService,
        protected CustomerIndexAction $customerIndexAction,
    ) {}

    /**
     * Get exchange rates for the transaction form.
     *
     * Branch-scoped, active, deduplicated: an unscoped ExchangeRate::all()
     * mixed every branch's overrides (and any legacy duplicate rows) into one
     * map, and mapWithKeys kept whichever row happened to be last.
     */
    public function getExchangeRates(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $branchId = $user?->branch_id;

        $rates = ExchangeRate::query()
            ->active()
            ->when(
                $branchId !== null,
                fn ($q) => $q->forBranchOrCompany($branchId),
                fn ($q) => $q->whereNull('branch_id')
            )
            ->orderByRaw('branch_id IS NULL')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->get()
            ->unique('currency_code')
            ->mapWithKeys(fn ($r) => [$r->currency_code => [
                'buy' => (float) $r->rate_buy,
                'sell' => (float) $r->rate_sell,
                'rate_unit' => (int) $r->rate_unit,
                'rate_inverse' => (bool) $r->rate_inverse,
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
        $filters = [
            'search' => $request->get('search'),
            'risk_rating' => $request->get('risk_rating'),
            'nationality' => $request->get('nationality'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_dir' => $request->get('sort_dir', 'desc'),
            'per_page' => 20,
            'branch_scope' => null,
        ];

        // Boolean filters are only included when actually supplied: the index
        // action treats a present key as an explicit filter, so always adding
        // them would force every plain listing into inactive-only results.
        foreach (['is_active', 'pep_status'] as $booleanFilter) {
            if (($value = $request->get($booleanFilter)) !== null && $value !== '') {
                $filters[$booleanFilter] = $value;
            }
        }

        $customers = $this->customerIndexAction->execute(
            $filters,
            $request->user()
        )->withQueryString();

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
        ['idTypes' => $idTypes, 'nationalities' => $nationalities] = $this->getCustomerFormOptions();

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
            $result = $this->customerService->createCustomerAction($validated, (int) auth()->id());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb(
                $e,
                'Customer store failed',
                'Failed to create customer. Please contact support.'
            );
        }

        return redirect()->route('customers.show', $result->customer)
            ->with('success', $result->message);
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

        // Aggregate transaction stats in a single query instead of separate loadCount/loadSum/loadAvg
        $stats = $customer->transactions()
            ->selectRaw('COUNT(*) as count, SUM(amount_local) as sum, AVG(amount_local) as avg, MAX(created_at) as last_at')
            ->first();

        $notes = $customer->notes()
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate transaction stats
        $transactionStats = [
            'total_transactions' => (int) ($stats->count ?? 0),
            'total_volume' => $stats->sum ?? 0,
            'avg_transaction' => $stats->avg ?? 0,
            'last_transaction' => $stats->last_at ?? ($customer->transactions->first()?->created_at ?? null),
        ];

        // Get customer show data from service (document status and compliance stats)
        $customerShowData = $this->customerService->getCustomerShowData($customer);

        $decryptedPhone = $this->customerService->decryptPhone($customer);

        return view('customers.show', compact(
            'customer',
            'transactionStats',
            'notes',
            'customerShowData',
            'decryptedPhone'
        ));
    }

    /**
     * Store a new note for the specified customer.
     */
    public function storeNote(StoreCustomerNoteRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('createNote', $customer);

        $customer->notes()->create([
            'note' => $request->validated('note'),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Freeze the specified customer (compliance/admin only).
     *
     * Route wiring (central): POST customers/{customer}/freeze with
     * role:compliance,admin middleware. The inline role gate below keeps the
     * endpoint safe even if the route is registered without it.
     */
    public function freeze(FreezeCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->role?->canPerform(Permission::AccessCompliance)) {
            abort(403, 'Unauthorized. Access Compliance permission required.');
        }

        if ($customer->is_frozen) {
            return back()->with('error', 'Customer is already frozen.');
        }

        try {
            $customer->freeze($request->validated('reason'));
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb(
                $e,
                'Customer freeze failed',
                'Failed to freeze customer. Please contact support.',
                ['customer_id' => $customer->id]
            );
        }

        $this->auditService->logCustomerEvent('customer_frozen', $customer->id, [
            'user_id' => $user->id,
            'new_values' => [
                'is_frozen' => true,
                'freeze_reason' => $customer->freeze_reason,
                'frozen_at' => optional($customer->frozen_at)->toIso8601String(),
            ],
        ], 'WARNING');

        return back()->with('success', 'Customer frozen.');
    }

    /**
     * Unfreeze the specified customer (compliance/admin only).
     *
     * Route wiring (central): POST customers/{customer}/unfreeze with
     * role:compliance,admin middleware.
     */
    public function unfreeze(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->role?->canPerform(Permission::AccessCompliance)) {
            abort(403, 'Unauthorized. Access Compliance permission required.');
        }

        if (! $customer->is_frozen) {
            return back()->with('error', 'Customer is not frozen.');
        }

        try {
            $customer->unfreeze();
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb(
                $e,
                'Customer unfreeze failed',
                'Failed to unfreeze customer. Please contact support.',
                ['customer_id' => $customer->id]
            );
        }

        $this->auditService->logCustomerEvent('customer_unfrozen', $customer->id, [
            'user_id' => $user->id,
            'old_values' => [
                'is_frozen' => true,
                'frozen_at' => null,
            ],
            'new_values' => [
                'is_frozen' => false,
            ],
        ]);

        return back()->with('success', 'Customer unfrozen.');
    }

    /**
     * Close the specified customer (manager/admin only).
     *
     * Route wiring (central): POST customers/{customer}/close with
     * role:manager,admin middleware. The inline role gate below keeps the
     * endpoint safe even if the route is registered without it.
     */
    public function close(CloseCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->role->canPerform(Permission::ManageCustomers)) {
            abort(403, 'Unauthorized. Manage Customers permission required.');
        }

        if ($customer->closed_at !== null) {
            return back()->with('error', 'Customer is already closed.');
        }

        $blocking = $customer->openBlockingTransactions();
        if ($blocking->isNotEmpty()) {
            return back()->with('error', sprintf(
                'Cannot close customer: %d transaction(s) pending approval or cancellation. Resolve them first.',
                $blocking->count()
            ));
        }

        try {
            $this->customerService->closeCustomer($customer, $request->validated('reason'), $user);
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb(
                $e,
                'Customer closure failed',
                'Failed to close customer. Please contact support.',
                ['customer_id' => $customer->id]
            );
        }

        return back()->with('success', 'Customer closed.');
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        ['idTypes' => $idTypes, 'nationalities' => $nationalities] = $this->getCustomerFormOptions();
        $riskRatings = ['Low', 'Medium', 'High'];

        // Decrypt ID number for display
        $decryptedIdNumber = $this->customerService->decryptIdNumber($customer);
        $decryptedPhone = $this->customerService->decryptPhone($customer);
        $decryptedAddress = $this->customerService->decryptAddress($customer);

        return view('customers.edit', compact(
            'customer',
            'idTypes',
            'riskRatings',
            'nationalities',
            'decryptedIdNumber',
            'decryptedPhone',
            'decryptedAddress'
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
            $result = $this->customerService->updateCustomerAction($customer, $validated, (int) auth()->id());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb(
                $e,
                'Customer update failed',
                'Failed to update customer. Please contact support.',
                ['customer_id' => $customer->id]
            );
        }

        return redirect()->route('customers.show', $result->customer)
            ->with('success', $result->message);
    }

    /**
     * Get the option lists shared by customer create/edit forms.
     *
     * @return array{idTypes: array<string, string>, nationalities: list<string>}
     */
    private function getCustomerFormOptions(): array
    {
        return [
            'idTypes' => [
                'MyKad' => 'MyKad (Malaysian IC)',
                'Passport' => 'Passport',
                'Others' => 'Other ID',
            ],
            'nationalities' => [
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
            ],
        ];
    }
}
