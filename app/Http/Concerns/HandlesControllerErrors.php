<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the 62 duplicated controller blocks that looked like:
 *   try { ... } catch (\Exception $e) {
 *       Log::error('...', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
 *       return back()/errorResponse(...);
 *   }
 *
 * Two helpers cover the two response shapes (web / API). Each controller
 * still chooses its user-facing message, keeping domain intent local.
 */
trait HandlesControllerErrors
{
    /**
     * Log an error with the standard controller context fields
     * (user_id, controller, action, route) and return a web fallback.
     *
     * @param  string  $context  Domain-specific log context key (e.g., 'Customer store failed')
     * @param  string  $message  User-facing error message
     * @param  array  $extra  Additional log fields (e.g., customer_id)
     */
    protected function handleExceptionWeb(
        \Throwable $e,
        string $context,
        string $message,
        array $extra = []
    ): RedirectResponse {
        // Service-layer validation failures (e.g. duplicate ID number) carry
        // field messages — let the framework redirect back with them rather
        // than masking them behind the generic fallback.
        if ($e instanceof ValidationException) {
            throw $e;
        }

        Log::error($context, array_merge([
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
            'exception_class' => $e::class,
            'controller' => static::class,
            'action' => __FUNCTION__,
            'trace' => $e->getTraceAsString(),
        ], $extra));

        return back()->with('error', $message)->withInput();
    }

    /**
     * Log an error with the standard controller context fields and
     * return an API JSON error response.
     *
     * @param  string  $context  Domain-specific log context key
     * @param  string  $message  User-facing error message
     * @param  int  $status  HTTP status (default 500)
     * @param  array  $extra  Additional log fields
     */
    protected function handleExceptionApi(
        \Throwable $e,
        string $context,
        string $message,
        int $status = 500,
        array $extra = []
    ): JsonResponse {
        // Same passthrough as the web variant — a service-layer
        // ValidationException should surface as a 422 with field errors,
        // not a generic 500.
        if ($e instanceof ValidationException) {
            throw $e;
        }

        Log::error($context, array_merge([
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
            'exception_class' => $e::class,
            'controller' => static::class,
            'action' => __FUNCTION__,
            'trace' => $e->getTraceAsString(),
        ], $extra));

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Try a callable and return either its result or a web fallback on
     * exception.
     *
     * Prefer the explicit try/catch pattern for visible domain intent:
     *
     *   try {
     *       $result = $this->customerService->createCustomerAction($validated, auth()->id());
     *   } catch (\Throwable $e) {
     *       return $this->handleExceptionWeb(
     *           $e,
     *           'Customer store failed',
     *           'Failed to create customer. Please contact support.'
     *       );
     *   }
     */
    protected function withWebError(callable $fn, string $context, string $message, array $extra = []): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, $context, $message, $extra);
        }
    }

    /**
     * Try a callable and return either its result or an API JSON error
     * response on exception.
     */
    protected function withApiError(callable $fn, string $context, string $message, int $status = 500, array $extra = []): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $this->handleExceptionApi($e, $context, $message, $status, $extra);
        }
    }
}
