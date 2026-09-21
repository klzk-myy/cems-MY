<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesCounter;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\AcknowledgeHandoverRequest;
use App\Models\Counter;
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
        if (! $counter instanceof Counter) {
            return $this->notFoundResponse('Counter not found');
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
        } catch (PermissionDeniedException $e) {
            return $this->domainErrorResponse($e, 'You are not authorized to acknowledge this handover.');
        } catch (InvalidStateException $e) {
            return $this->errorResponse('The handover is no longer in a valid state for acknowledgment.', [], 422);
        }
    }
}
