<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function batchScreen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $results = $this->screeningService->batchScreen($validated['customer_ids']);

        return response()->json([
            'data' => $results->map(fn ($r, $id) => array_merge(['customer_id' => $id], $r->toArray()))->values()->toArray(),
        ]);
    }
}
