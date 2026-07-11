<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesCounter;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\InitiateEmergencyCloseRequest;
use App\Models\EmergencyClosure;
use App\Services\Branch\EmergencyCounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmergencyCounterController extends Controller
{
    use ApiResponse;
    use AuthorizesCounter;

    public function __construct(
        protected EmergencyCounterService $emergencyService
    ) {}

    public function initiateClose(InitiateEmergencyCloseRequest $request, int $counterId): JsonResponse
    {
        $validated = $request->validated();

        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $user = Auth::user();

        try {
            $closure = $this->emergencyService->initiateEmergencyClose(
                $counter,
                $user,
                $validated['reason']
            );

            return $this->successResponse($closure, 'Emergency closure initiated successfully', 201);
        } catch (EmergencyCloseCooldownException $e) {
            return $this->errorResponse($e->getMessage(), [], 429);
        } catch (EmergencyCloseSessionTooNewException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    public function getVariance(int $counterId, int $closureId): JsonResponse
    {
        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $closure = EmergencyClosure::find($closureId);
        if (! $closure || $closure->counter_id !== $counter->id) {
            return $this->notFoundResponse('Closure not found for this counter');
        }

        $variance = $this->emergencyService->getVariance($closure);

        return $this->successResponse($variance);
    }

    public function acknowledge(int $counterId, int $closureId): JsonResponse
    {
        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $closure = EmergencyClosure::find($closureId);
        if (! $closure || $closure->counter_id !== $counter->id) {
            return $this->notFoundResponse('Closure not found for this counter');
        }

        $user = Auth::user();

        if (! $user->isManager()) {
            return $this->errorResponse('Only managers can acknowledge emergency closures', [], 403);
        }

        $closure = $this->emergencyService->acknowledge($closure, $user);

        return $this->successResponse($closure, 'Emergency closure acknowledged');
    }
}
