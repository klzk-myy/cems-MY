<?php

namespace App\Http\Middleware;

use App\Services\QueryLoggingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryLogging
{
    public function __construct(
        private QueryLoggingService $queryLoggingService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('database.logging')) {
            return $next($request);
        }

        try {
            $this->queryLoggingService->enable();

            $response = $next($request);

            $this->queryLoggingService->analyzeAndLog($request);

            return $response;
        } catch (\Exception $e) {
            Log::error('Query logging middleware error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $next($request);
        }
    }
}
