<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CloseCounterRequest;
use App\Models\Counter;
use App\Services\Branch\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CounterApiController extends Controller
{
    public function __construct(
        protected CounterService $counterService
    ) {}

    public function close(CloseCounterRequest $request, string $counterId): JsonResponse
    {
        $validated = $request->validated();

        $counter = Counter::findOrFail($counterId);

        $session = $counter->sessions()
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No open session found for this counter',
            ], 404);
        }

        try {
            $result = $this->counterService->closeSession(
                $session,
                $request->user(),
                $validated['closing_floats'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter closed successfully',
                'session' => $result['session'] ?? $session->fresh(),
            ]);
        } catch (SessionClosedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (VarianceThresholdException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to close counter', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Operation failed. Please contact support.',
            ], 500);
        }
    }
}
