<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Screening\BatchScreenRequest;
use App\Http\Requests\Api\V1\ScreeningRequest;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Http\JsonResponse;

class ScreeningController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerScreeningService $screeningService,
    ) {}

    public function screen(ScreeningRequest $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $notes = $request->input('notes');

        $response = $this->screeningService->screenCustomer($customer, $notes);

        return $this->successResponse($response->toArray());
    }

    public function history(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $history = $this->screeningService->getHistory($customer);

        return $this->successResponse($history->map(fn ($r) => $r->toArray())->toArray());
    }

    public function status(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $status = $this->screeningService->getStatus($customer);

        return $this->successResponse($status);
    }

    public function batchScreen(BatchScreenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->screeningService->batchScreen($validated['customer_ids']);

        return $this->successResponse($results->map(fn ($r, $id) => array_merge(['customer_id' => $id], $r->toArray()))->values()->toArray());
    }
}
