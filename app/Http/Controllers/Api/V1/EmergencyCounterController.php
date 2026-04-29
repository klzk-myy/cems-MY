<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\EmergencyClosure;
use App\Services\EmergencyCounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmergencyCounterController extends Controller
{
    public function __construct(
        protected EmergencyCounterService $emergencyService
    ) {}

    public function initiateClose(Request $request, int $counterId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        $user = Auth::user();

        try {
            $closure = $this->emergencyService->initiateEmergencyClose(
                $counter,
                $user,
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'data' => $closure,
                'message' => 'Emergency closure initiated successfully',
            ], 201);
        } catch (EmergencyCloseCooldownException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (EmergencyCloseSessionTooNewException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getVariance(int $counterId, int $closureId): JsonResponse
    {
        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        $closure = EmergencyClosure::find($closureId);
        if (! $closure || $closure->counter_id !== $counter->id) {
            return response()->json([
                'success' => false,
                'message' => 'Closure not found for this counter',
            ], 404);
        }

        $variance = $this->emergencyService->getVariance($closure);

        return response()->json([
            'success' => true,
            'data' => $variance,
        ]);
    }

    public function acknowledge(Request $request, int $counterId, int $closureId): JsonResponse
    {
        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        $closure = EmergencyClosure::find($closureId);
        if (! $closure || $closure->counter_id !== $counter->id) {
            return response()->json([
                'success' => false,
                'message' => 'Closure not found for this counter',
            ], 404);
        }

        $user = Auth::user();

        if (! $user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers can acknowledge emergency closures',
            ], 403);
        }

        $closure = $this->emergencyService->acknowledge($closure, $user);

        return response()->json([
            'success' => true,
            'data' => $closure,
            'message' => 'Emergency closure acknowledged',
        ]);
    }
}
