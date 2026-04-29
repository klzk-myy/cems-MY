<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Services\CounterHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CounterHandoverController extends Controller
{
    public function __construct(
        protected CounterHandoverService $handoverService
    ) {}

    public function acknowledge(Request $request, int $counterId, int $handoverId): JsonResponse
    {
        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        $handover = CounterHandover::with('counterSession')->find($handoverId);
        if (! $handover || $handover->counterSession->counter_id !== $counterId) {
            return response()->json([
                'success' => false,
                'message' => 'Handover not found for this counter',
            ], 404);
        }

        $validated = $request->validate([
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        try {
            $this->handoverService->acknowledgeHandover(
                $handover,
                $user,
                $validated['verified'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Handover acknowledged successfully',
            ]);
        } catch (UnauthorizedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidStateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
