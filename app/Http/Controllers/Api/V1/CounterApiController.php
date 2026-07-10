<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CloseCounterRequest;
use App\Models\Counter;
use App\Services\Branch\CounterService;
use Illuminate\Http\JsonResponse;

class CounterApiController extends Controller
{
    use ApiResponse;

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
            return $this->notFoundResponse('No open session found for this counter');
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
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (VarianceThresholdException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Operation failed. Please contact support.', $e);
        }
    }
}
