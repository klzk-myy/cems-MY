<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Counter;
use App\Models\User;
use Illuminate\Http\JsonResponse;

trait AuthorizesCounter
{
    protected function authorizeCounter(int $counterId, ?User $user = null): Counter|JsonResponse
    {
        $user ??= auth()->user();
        $counter = Counter::find($counterId);

        if (! $counter) {
            return $this->notFoundResponse('Counter not found');
        }

        if (! $user?->isAdmin() && $counter->branch_id !== $user?->branch_id) {
            return $this->errorResponse('You do not have permission to access this resource.', [], 403);
        }

        return $counter;
    }
}
