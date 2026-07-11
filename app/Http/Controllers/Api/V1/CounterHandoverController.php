<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesCounter;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\AcknowledgeHandoverRequest;
use App\Models\CounterHandover;
use App\Services\Branch\CounterHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CounterHandoverController extends Controller
{
    use ApiResponse;
    use AuthorizesCounter;

    public function __construct(
        protected CounterHandoverService $handoverService
    ) {}

    public function acknowledge(AcknowledgeHandoverRequest $request, int $counterId, int $handoverId): JsonResponse
    {
        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $handover = CounterHandover::with('counterSession')->find($handoverId);
        if (! $handover || $handover->counterSession->counter_id !== $counterId) {
            return $this->notFoundResponse('Handover not found for this counter');
        }

        $validated = $request->validated();

        try {
            $this->handoverService->acknowledgeHandover(
                $handover,
                Auth::user(),
                $validated['verified'],
                $validated['notes'] ?? null
            );

            return $this->successResponse(null, 'Handover acknowledged successfully');
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (InvalidStateException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }
    }
}
