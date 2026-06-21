<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CddLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCreateCustomerRequest;
use App\Http\Requests\SearchCustomerRequest;
use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerSearchController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
    ) {}

    /**
     * Search customers for transaction form autocomplete.
     */
    public function search(SearchCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->customerService->searchCustomers($validated['query']);

        return response()->json([
            'success' => true,
            'query' => $validated['query'],
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Quick create customer from transaction form.
     * Used when customer not found in database.
     */
    public function quickCreate(QuickCreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        $customer = $this->customerService->createCustomer($validated, auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'ic_number_masked' => $customer->ic_number,
                'nationality' => $customer->nationality,
                'risk_rating' => $customer->risk_rating,
                'cdd_level' => $customer->cdd_level instanceof CddLevel ? $customer->cdd_level->value : $customer->cdd_level,
                'is_pep' => $customer->pep_status,
                'is_sanctioned' => $customer->sanction_hit,
            ],
        ]);
    }
}
