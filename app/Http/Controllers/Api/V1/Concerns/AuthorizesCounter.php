<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Counter;
use Illuminate\Http\JsonResponse;

trait AuthorizesCounter
{
    use AuthorizesBranchResource;

    protected function authorizeCounter(int $counterId, ?string $message = null): Counter|JsonResponse
    {
        $counter = Counter::find($counterId);

        if (! $counter) {
            return $this->notFoundResponse('Counter not found');
        }

        $result = $this->authorizeBranchResource($counter, 'access', $message);

        return $result instanceof JsonResponse ? $result : $counter;
    }
}
