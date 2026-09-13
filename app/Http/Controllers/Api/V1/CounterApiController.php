<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesCloseSupervisor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CloseCounterRequest;
use App\Http\Requests\Api\V1\Counter\StoreCounterRequest;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Services\Branch\CounterService;
use Illuminate\Http\JsonResponse;

class CounterApiController extends Controller
{
    use ApiResponse;
    use ResolvesCloseSupervisor;

    public function __construct(
        protected CounterService $counterService
    ) {}

    /**
     * Register a counter at a trading branch via CounterService — the same
     * path as the web store, so both surfaces produce identical outcomes.
     */
    public function store(StoreCounterRequest $request): JsonResponse
    {
        $counter = $this->counterService->createCounter(
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(['counter' => $counter], 'Counter created successfully', 201);
    }

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

        // The API takes closing_floats as a currency => amount map; the
        // service expects [{currency_id, amount}] items. A raw map would be
        // indexed as $float['currency_id'] on a scalar and 500.
        $closingFloats = [];
        foreach ($validated['closing_floats'] as $currencyCode => $amount) {
            $closingFloats[] = [
                'currency_id' => $currencyCode,
                'amount' => $amount,
            ];
        }

        try {
            $supervisor = $this->resolveCloseSupervisor(
                $request->user(),
                isset($validated['supervisor_id']) ? (int) $validated['supervisor_id'] : null
            );
        } catch (InvalidStateException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }

        try {
            $result = $this->counterService->closeSession(
                $session,
                $request->user(),
                $closingFloats,
                $validated['notes'] ?? null,
                $supervisor
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
