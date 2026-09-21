<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Models\Counter;

trait AuthorizesCounter
{
    use AuthorizesBranchResource;

    /**
     * Load a counter and authorize it against the caller's branch.
     * Throws on denial; returns null only when the counter is missing.
     */
    protected function authorizeCounter(int $counterId, ?string $message = null): ?Counter
    {
        $counter = Counter::find($counterId);

        if (! $counter) {
            return null;
        }

        $this->authorizeBranchResource($counter, 'access', $message);

        return $counter;
    }
}
