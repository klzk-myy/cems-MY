<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CounterSessionStatus;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Http\Controllers\Concerns\ResolvesCloseSupervisor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CloseCounterRequest;
use App\Http\Requests\Api\V1\Counter\StoreCounterRequest;
use App\Http\Resources\Api\V1\CounterResource;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Services\Branch\CounterService;
use Illuminate\Http\JsonResponse;

class CounterApiController extends Controller
{
    use ApiResponse;
    use AuthorizesBranchResource;
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

        return $this->successResponse(['counter' => new CounterResource($counter)], 'Counter created successfully', 201);
    }

    public function close(CloseCounterRequest $request, string $counterId): JsonResponse
    {
        $validated = $request->validated();

        $counter = Counter::findOrFail($counterId);

        $branchError = $this->authorizeBranchResource($counter, 'access', 'You do not have access to counters in this branch.');
        if ($branchError instanceof JsonResponse) {
            return $branchError;
        }

        /** @var CounterSession|null $session */
        $session = $counter->sessions()
            ->where('status', CounterSessionStatus::Open->value)
            ->latest()
            ->first();

        if (! $session) {
            return $this->notFoundResponse('No open session found for this counter');
        }

        // The API takes closing_floats as a currency => amount map; the
        // service expects [{currency_id, quantity}] items. A raw map would be
        // indexed as $float['currency_id'] on a scalar and 500.
        $closingFloats = [];
        foreach ($validated['closing_floats'] as $currencyCode => $quantity) {
            $closingFloats[] = [
                'currency_id' => $currencyCode,
                'quantity' => $quantity,
            ];
        }

        try {
            $supervisor = $this->resolveCloseSupervisor(
                $request->user(),
                isset($validated['supervisor_id']) ? (int) $validated['supervisor_id'] : null
            );
        } catch (InvalidStateException $e) {
            return $this->domainErrorResponse($e);
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
            return $this->domainErrorResponse($e, 'The counter session has already been closed.');
        } catch (VarianceThresholdException $e) {
            return $this->domainErrorResponse($e, 'Variance threshold exceeded. Supervisor review required.');
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Operation failed. Please contact support.', $e);
        }
    }
}
