<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Helpers for returning a 403 response when the authenticated user
 * is not a manager or admin.
 *
 * Host controllers must provide `auth()->user()` with an `isManager()` method.
 */
trait EnsuresManagerOrAdmin
{
    /**
     * Return a 403 response if the current user is not a manager or admin.
     *
     * @param  callable(): JsonResponse  $responseFactory
     * @return JsonResponse|null The 403 response, or null when authorized.
     */
    protected function ensureManagerOrAdminResponse(callable $responseFactory): ?JsonResponse
    {
        if (auth()->user()->isManager()) {
            return null;
        }

        return $responseFactory();
    }
}
