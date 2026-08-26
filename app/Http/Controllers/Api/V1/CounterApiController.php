<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CloseCounterRequest;
use App\Models\Counter;
use App\Models\CounterSession;
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

        $branchError = $this->ensureCounterBranchAccess($request, $counter);
        if ($branchError !== null) {
            return $branchError;
        }

        /** @var CounterSession|null $session */
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

            return $this->successResponse(null, 'Counter closed successfully', 200, [
                'session' => $result['session'] ?? $session->fresh(),
            ]);
        } catch (SessionClosedException $e) {
            return $this->errorResponse('The counter session has already been closed.', [], 422);
        } catch (VarianceThresholdException $e) {
            return $this->errorResponse('Variance threshold exceeded. Supervisor review required.', [], 422);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Operation failed. Please contact support.', $e);
        }
    }

    /**
     * Enforce branch isolation: non-admins may only close counters belonging
     * to their own branch. Mirrors the web CounterController guard so the API
     * surface cannot be used to close another branch's sessions.
     */
    private function ensureCounterBranchAccess(CloseCounterRequest $request, Counter $counter): ?JsonResponse
    {
        $user = $request->user();

        if ($user->role->canManageAllBranches()) {
            return null;
        }

        if ($counter->branch_id !== $user->branch_id) {
            return $this->errorResponse('You do not have access to counters in this branch.', [], 403);
        }

        return null;
    }
}
