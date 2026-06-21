<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\HandoverCounterRequest;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Services\Branch\CounterHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CounterHandoverController extends Controller
{
    public function __construct(
        protected CounterHandoverService $handoverService
    ) {}

    public function acknowledge(HandoverCounterRequest $request, int $counterId, int $handoverId): JsonResponse
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

        $user = Auth::user();

        if ($user->role !== UserRole::Admin && $counter->branch_id !== $user->branch_id) {
            abort(403, 'You do not have permission to access this resource.');
        }

        $validated = $request->validated();

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
