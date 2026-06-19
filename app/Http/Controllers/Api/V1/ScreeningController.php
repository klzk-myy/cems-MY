<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Screening\BatchScreenRequest;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningController extends Controller
{
    public function __construct(
        protected CustomerScreeningService $screeningService,
    ) {}

    public function screen(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $notes = $request->input('notes');

        $response = $this->screeningService->screenCustomer($customer, $notes);

        return response()->json([
            'data' => $response->toArray(),
        ]);
    }

    public function history(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $history = $this->screeningService->getHistory($customer);

        return response()->json([
            'data' => $history->map(fn ($r) => $r->toArray())->toArray(),
        ]);
    }

    public function status(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $status = $this->screeningService->getStatus($customer);

        return response()->json([
            'data' => $status,
        ]);
    }

    public function batchScreen(BatchScreenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->screeningService->batchScreen($validated['customer_ids']);

        return response()->json([
            'data' => $results->map(fn ($r, $id) => array_merge(['customer_id' => $id], $r->toArray()))->values()->toArray(),
        ]);
    }
}
