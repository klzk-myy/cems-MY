<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;

trait AuthorizesManager
{
    protected function requireManagerOrAdminResponse(string $message = 'Unauthorized. Manager or Admin access required.'): ?JsonResponse
    {
        if (! auth()->user()?->isManager()) {
            return $this->errorResponse($message, [], 403);
        }

        return null;
    }
}
