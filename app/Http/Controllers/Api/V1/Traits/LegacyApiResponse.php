<?php

namespace App\Http\Controllers\Api\V1\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Explicit marker for the few endpoints that intentionally emit a
 * non-standard envelope (no {success, message, data|errors} wrapper).
 *
 * These shapes are preserved verbatim for existing API consumers and must
 * not be used as the template for new endpoints — those use ApiResponse.
 */
trait LegacyApiResponse
{
    /**
     * Emit a legacy non-standard JSON payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function legacyJsonResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }
}
