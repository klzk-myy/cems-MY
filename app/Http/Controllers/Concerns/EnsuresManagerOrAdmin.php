<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Helpers for returning a 403 response when the authenticated user
 * is not a manager or admin.
 *
 * Two usage styles:
 *   - `ensureManagerOrAdminResponse(callable)` — callers supply their own
 *     response body (used by RateController with branch-specific messages).
 *   - `requireManagerOrAdminResponse(?string)` — returns a standardized
 *     ApiResponse-formatted 403 (used by API controllers with only a message).
 *
 * Host controllers using the second style must provide an `errorResponse()`
 * helper (e.g. by using the `ApiResponse` trait).
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
        $user = auth()->user();

        if ($user && $user->isManager()) {
            return null;
        }

        return $responseFactory();
    }

    /**
     * Return a standardized 403 API response if the current user is not a
     * manager or admin.
     *
     * Requires the host controller to provide an `errorResponse()` method
     * (typically via the `ApiResponse` trait).
     */
    protected function requireManagerOrAdminResponse(string $message = 'Unauthorized. Manager or Admin access required.'): ?JsonResponse
    {
        return $this->ensureManagerOrAdminResponse(
            fn (): JsonResponse => $this->errorResponse($message, [], 403)
        );
    }
}
