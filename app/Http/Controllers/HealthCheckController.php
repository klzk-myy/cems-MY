<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthCheckController extends Controller
{
    public function __construct(
        protected CacheFactory $cache
    ) {}

    /**
     * Health check endpoint - verifies system dependencies
     *
     * Returns: {"status": "healthy"|"unhealthy", "checks": {...}, "timestamp": "..."}
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache connectivity
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_'.time();
            $this->cache->put($testKey, 'test', 10);
            $value = $this->cache->get($testKey);
            $this->cache->forget($testKey);

            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache connection successful',
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Cache read/write test failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
            ];
        }
    }

    /**
     * Check queue connectivity
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $queueDriver = config("queue.connections.{$connection}.driver", $connection);

            // For database queue, verify table exists
            if ($queueDriver === 'database') {
                DB::table('jobs')->count();
            }

            return [
                'status' => 'healthy',
                'message' => "Queue connection ({$queueDriver}) successful",
                'driver' => $queueDriver,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue connection failed',
            ];
        }
    }
}
