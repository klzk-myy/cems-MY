<?php

namespace App\Services;

use App\Models\SystemHealthCheck;
use App\Models\TestResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemHealthService
{
    /**
     * Run all health checks and return results
     */
    public function runAllChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
            'tests' => $this->checkTests(),
        ];

        // Store results in database
        foreach ($checks as $name => $result) {
            $this->storeCheckResult($name, $result);
        }

        return $checks;
    }

    /**
     * Check database connectivity
     */
    public function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => SystemHealthCheck::STATUS_OK,
                'message' => "Database connection successful ({$responseTime}ms)",
                'details' => [
                    'driver' => DB::connection()->getDriverName(),
                    'database' => DB::connection()->getDatabaseName(),
                    'response_time_ms' => $responseTime,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_CRITICAL,
                'message' => 'Database connection failed: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check cache connectivity
     */
    public function checkCache(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_'.time();
            $testValue = 'test_value_'.time();

            // Use default cache store to avoid Redis extension dependency
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved === $testValue) {
                $driver = config('cache.default');

                return [
                    'status' => SystemHealthCheck::STATUS_OK,
                    'message' => "Cache ({$driver}) operational ({$responseTime}ms)",
                    'details' => [
                        'driver' => $driver,
                        'response_time_ms' => $responseTime,
                    ],
                ];
            }

            return [
                'status' => SystemHealthCheck::STATUS_WARNING,
                'message' => 'Cache read/write mismatch',
                'details' => [
                    'expected' => $testValue,
                    'got' => $retrieved,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_CRITICAL,
                'message' => 'Cache unavailable: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check queue connectivity
     */
    public function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            // Check if queue driver is available
            $connection = Queue::connection();

            // For Redis queue, test basic connectivity
            if ($driver === 'redis') {
                $start = microtime(true);
                $connection->size('default');
                $responseTime = round((microtime(true) - $start) * 1000, 2);

                return [
                    'status' => SystemHealthCheck::STATUS_OK,
                    'message' => "Queue (Redis) operational ({$responseTime}ms)",
                    'details' => [
                        'driver' => $driver,
                        'response_time_ms' => $responseTime,
                    ],
                ];
            }

            // For database queue, check table exists
            if ($driver === 'database') {
                $count = DB::table('jobs')->count();

                return [
                    'status' => SystemHealthCheck::STATUS_OK,
                    'message' => "Queue (Database) operational ({$count} jobs)",
                    'details' => [
                        'driver' => $driver,
                        'pending_jobs' => $count,
                    ],
                ];
            }

            // For sync queue (development)
            if ($driver === 'sync') {
                return [
                    'status' => SystemHealthCheck::STATUS_WARNING,
                    'message' => 'Queue running in sync mode (development only)',
                    'details' => [
                        'driver' => $driver,
                    ],
                ];
            }

            return [
                'status' => SystemHealthCheck::STATUS_OK,
                'message' => "Queue ({$driver}) operational",
                'details' => [
                    'driver' => $driver,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_CRITICAL,
                'message' => 'Queue check failed: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check disk space
     */
    public function checkDiskSpace(): array
    {
        try {
            $path = storage_path();
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);

            // Calculate thresholds
            $freeGB = round($freeSpace / (1024 * 1024 * 1024), 2);
            $totalGB = round($totalSpace / (1024 * 1024 * 1024), 2);
            $usedGB = round($usedSpace / (1024 * 1024 * 1024), 2);

            $status = SystemHealthCheck::STATUS_OK;
            $message = "Disk usage: {$usagePercent}% ({$freeGB}GB free)";

            if ($usagePercent > 95) {
                $status = SystemHealthCheck::STATUS_CRITICAL;
                $message = "CRITICAL: Disk usage at {$usagePercent}% (only {$freeGB}GB free)";
            } elseif ($usagePercent > 85) {
                $status = SystemHealthCheck::STATUS_WARNING;
                $message = "WARNING: Disk usage at {$usagePercent}% ({$freeGB}GB free)";
            }

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'total_gb' => $totalGB,
                    'used_gb' => $usedGB,
                    'free_gb' => $freeGB,
                    'usage_percent' => $usagePercent,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_WARNING,
                'message' => 'Disk space check failed: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check memory usage
     */
    public function checkMemory(): array
    {
        try {
            // Get current memory usage
            $currentUsage = memory_get_usage(true);
            $peakUsage = memory_get_peak_usage(true);

            // Get memory limit
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);

            $currentMB = round($currentUsage / (1024 * 1024), 2);
            $peakMB = round($peakUsage / (1024 * 1024), 2);
            $limitMB = $memoryLimitBytes > 0 ? round($memoryLimitBytes / (1024 * 1024), 2) : 'unlimited';

            $status = SystemHealthCheck::STATUS_OK;
            $message = "Memory: {$currentMB}MB current, {$peakMB}MB peak";

            if ($memoryLimitBytes > 0) {
                $usagePercent = round(($currentUsage / $memoryLimitBytes) * 100, 2);

                if ($usagePercent > 95) {
                    $status = SystemHealthCheck::STATUS_CRITICAL;
                    $message = "CRITICAL: Memory usage at {$usagePercent}% ({$currentMB}MB / {$limitMB}MB)";
                } elseif ($usagePercent > 80) {
                    $status = SystemHealthCheck::STATUS_WARNING;
                    $message = "WARNING: Memory usage at {$usagePercent}% ({$currentMB}MB / {$limitMB}MB)";
                } else {
                    $message .= " ({$usagePercent}% of {$limitMB}MB limit)";
                }
            }

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'current_mb' => $currentMB,
                    'peak_mb' => $peakMB,
                    'limit_mb' => $limitMB,
                    'limit_raw' => $memoryLimit,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_WARNING,
                'message' => 'Memory check failed: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check last test run status
     */
    public function checkTests(): array
    {
        try {
            $lastRun = TestResult::latest()->first();

            if (! $lastRun) {
                return [
                    'status' => SystemHealthCheck::STATUS_WARNING,
                    'message' => 'No test results found',
                    'details' => [
                        'last_run' => null,
                    ],
                ];
            }

            $status = SystemHealthCheck::STATUS_OK;
            $message = "Last test run: {$lastRun->pass_rate}% pass rate";

            if ($lastRun->status === 'failed') {
                $status = SystemHealthCheck::STATUS_CRITICAL;
                $message = "CRITICAL: Last test run failed ({$lastRun->failed} failures)";
            } elseif ($lastRun->pass_rate < 90) {
                $status = SystemHealthCheck::STATUS_WARNING;
                $message = "WARNING: Test pass rate low ({$lastRun->pass_rate}%)";
            }

            // Check if tests are stale (> 24 hours)
            $hoursAgo = $lastRun->created_at->diffInHours(now());
            if ($hoursAgo > 24 && $status === SystemHealthCheck::STATUS_OK) {
                $status = SystemHealthCheck::STATUS_WARNING;
                $message .= " (stale: {$hoursAgo}h ago)";
            }

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'last_run_id' => $lastRun->run_id,
                    'status' => $lastRun->status,
                    'pass_rate' => $lastRun->pass_rate,
                    'passed' => $lastRun->passed,
                    'failed' => $lastRun->failed,
                    'total' => $lastRun->total_tests,
                    'ran_at' => $lastRun->created_at->toIso8601String(),
                    'hours_ago' => $hoursAgo,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => SystemHealthCheck::STATUS_WARNING,
                'message' => 'Test status check failed: '.$e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Store a health check result
     */
    protected function storeCheckResult(string $name, array $result): SystemHealthCheck
    {
        return SystemHealthCheck::create([
            'check_name' => $name,
            'status' => $result['status'],
            'message' => $result['message'],
            'checked_at' => now(),
        ]);
    }

    /**
     * Get overall system status
     */
    public function getOverallStatus(): string
    {
        return SystemHealthCheck::getOverallStatus();
    }

    /**
     * Get current status summary
     */
    public function getStatusSummary(): array
    {
        $checks = SystemHealthCheck::getLatestChecks();
        $overallStatus = $this->getOverallStatus();

        $checkCount = [
            'ok' => 0,
            'warning' => 0,
            'critical' => 0,
            'unknown' => 0,
        ];

        foreach ($checks as $check) {
            if ($check === null) {
                $checkCount['unknown']++;
            } else {
                $checkCount[$check->status]++;
            }
        }

        return [
            'overall_status' => $overallStatus,
            'checks' => $checks,
            'summary' => $checkCount,
            'last_check' => SystemHealthCheck::latest()->first()?->checked_at,
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1; // Unlimited
        }

        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
