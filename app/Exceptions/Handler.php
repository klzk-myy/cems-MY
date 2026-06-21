<?php

namespace App\Exceptions;

use App\Exceptions\Domain\DomainException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Sanitizes API error responses to prevent information disclosure.
     */
    public function render(Request $request, Throwable $e): Response|JsonResponse|RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Handle domain exceptions with appropriate HTTP status codes
        if ($e instanceof DomainException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => $e->getErrorCode(),
                ], $e->getStatusCode());
            }

            return back()->with('error', $e->getMessage());
        }

        // Log all unhandled exceptions with full details for debugging
        Log::error('Unhandled exception', [
            'exception' => $e,
            'trace' => $e->getTraceAsString(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
        ]);

        // For API requests, return sanitized JSON response
        if ($request->expectsJson()) {
            $status = match (true) {
                $this->isHttpException($e) => $e->getStatusCode(),
                $e instanceof DomainException => $e->getStatusCode(),
                default => 500,
            };

            // Don't expose internal error details for 500 errors
            if ($status >= 500) {
                return response()->json([
                    'success' => false,
                    'message' => 'An internal error occurred. Please try again later.',
                    'code' => 'INTERNAL_ERROR',
                ], $status);
            }

            // For 4xx errors, use the exception message but ensure it's safe
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return parent::render($request, $e);
    }
}
