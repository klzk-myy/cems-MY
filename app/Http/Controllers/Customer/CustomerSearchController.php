<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CddLevel;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCreateCustomerRequest;
use App\Http\Requests\SearchCustomerRequest;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Services\Customer\CustomerService;
use App\Services\System\CacheKeys;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CustomerSearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerService $customerService,
    ) {}

    /**
     * Search customers for transaction form autocomplete.
     */
    public function search(SearchCustomerRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Enforce branch scoping for search - mirror CustomerPolicy::viewAny so a
        // teller/manager can never enumerate customers (or their full IC numbers)
        // from another branch.
        $branchId = null;
        if (! $user || ! $user->isAdmin()) {
            if (! $user?->branch_id) {
                return $this->successResponse([
                    'query' => $request->validated()['query'],
                    'results' => [],
                    'count' => 0,
                ]);
            }
            $branchId = $user->branch_id;
        }

        $validated = $request->validated();

        $results = $this->customerService->searchCustomers($validated['query'], $branchId);

        return $this->successResponse([
            'query' => $validated['query'],
            'results' => $results,
            'count' => count($results),
            'query_screening' => $this->customerService->screenSearchQuery($validated['query']),
        ]);
    }

    /**
     * Quick create customer from transaction form.
     * Used when customer not found in database.
     *
     * If the ID number is already registered, the existing customer is
     * returned with `existing: true` instead of a validation error — the
     * teller is registering a returning customer, not creating a duplicate.
     */
    public function quickCreate(QuickCreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        $existing = false;
        try {
            $customer = $this->customerService->createCustomer($validated, (int) auth()->id());
        } catch (ValidationException|QueryException $e) {
            // Whatever the validation failure was, if the submitted ID number
            // already belongs to an active customer this is a returning
            // customer — load that record instead of erroring. Anything else
            // (duplicate phone, soft-deleted ID) rethrows the original error.
            // QueryException covers the duplicate-identity race: the unique
            // blind-index constraint can reject a concurrent insert even
            // though the pre-check inside createCustomer passed.
            $customer = $this->customerService->findActiveByIdNumber($validated['id_number'] ?? '');
            if (! $customer) {
                throw $e;
            }
            $existing = true;
        }

        $exchangeRates = Cache::remember(CacheKeys::ExchangeRates->value, 300, fn () => ExchangeRate::all()
            ->mapWithKeys(fn ($r) => [$r->currency_code => [
                'buy' => $r->rate_buy,
                'sell' => $r->rate_sell,
                'rate_unit' => (int) $r->rate_unit,
                'rate_inverse' => (bool) $r->rate_inverse,
            ]])
            ->toArray()
        );

        return $this->successResponse([
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'id_number' => $customer->id_number,
                'nationality' => $customer->nationality,
                'risk_rating' => $customer->risk_rating,
                'cdd_level' => $customer->cdd_level instanceof CddLevel ? $customer->cdd_level->value : $customer->cdd_level,
                'is_pep' => $customer->pep_status,
                'is_sanctioned' => $customer->sanction_hit,
            ],
            'existing' => $existing,
            'exchange_rates' => $exchangeRates,
        ], $existing ? 'Customer already registered — loaded existing record' : 'Customer created successfully');
    }
}
