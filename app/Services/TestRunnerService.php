<?php

namespace App\Services;

use App\Models\TestResult;
use Illuminate\Support\Str;

class TestRunnerService
{
    /**
     * Run tests and save results to database
     */
    public function runTests(string $suite = 'full', array $options = []): TestResult
    {
        $runId = Str::uuid()->toString();

        // Create initial record
        $testResult = TestResult::create([
            'run_id' => $runId,
            'test_suite' => $suite,
            'status' => 'running',
            'started_at' => now(),
            'executed_by' => auth()->user()?->username ?? 'system',
            'git_branch' => $this->getGitBranch(),
            'git_commit' => $this->getGitCommit(),
        ]);

        try {
            // Build command
            $command = $this->buildCommand($suite, $options);

            // Run tests
            $output = [];
            $exitCode = 0;

            exec($command.' 2>&1', $output, $exitCode);
            $outputString = implode("\n", $output);

            // Parse results
            $parsed = $this->parseTestOutput($outputString);

            // Determine status
            $status = $this->determineStatus($exitCode, $parsed);

            // Extract failures and errors
            $failures = $this->extractFailures($outputString);
            $errors = $this->extractErrors($outputString);

            // Update record
            $testResult->update([
                'total_tests' => $parsed['total'] ?? 0,
                'passed' => $parsed['passed'] ?? 0,
                'failed' => $parsed['failed'] ?? 0,
                'skipped' => $parsed['skipped'] ?? 0,
                'assertions' => $parsed['assertions'] ?? 0,
                'duration' => $parsed['duration'] ?? 0,
                'status' => $status,
                'output' => $outputString,
                'failures' => $failures,
                'errors' => $errors,
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            $testResult->update([
                'status' => 'error',
                'errors' => [['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]],
                'completed_at' => now(),
            ]);
        }

        return $testResult->fresh();
    }

    /**
     * Build the artisan test command
     */
    protected function buildCommand(string $suite, array $options): string
    {
        $basePath = base_path();

        if ($suite === 'full') {
            return "cd {$basePath} && php artisan test";
        }

        return "cd {$basePath} && php artisan test --filter={$suite}";
    }

    /**
     * Parse test output to extract metrics
     */
    protected function parseTestOutput(string $output): array
    {
        $result = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'assertions' => 0,
            'duration' => 0,
        ];

        // Extract Tests: X passed (Y assertions)
        if (preg_match('/Tests:\s+(\d+)\s+passed\s+\((\d+)\s+assertions\)/', $output, $matches)) {
            $result['total'] = (int) $matches[1];
            $result['passed'] = (int) $matches[1];
            $result['assertions'] = (int) $matches[2];
        }

        // Extract duration
        if (preg_match('/Duration:\s+([\d.]+)s/', $output, $matches)) {
            $result['duration'] = (float) $matches[1];
        }

        // Count failures and passed tests from output
        $passedCount = substr_count($output, '✓');
        $failedCount = substr_count($output, 'FAIL');

        if ($passedCount > 0) {
            $result['passed'] = $passedCount;
        }
        if ($failedCount > 0) {
            $result['failed'] = $failedCount;
        }

        return $result;
    }

    /**
     * Determine overall status
     */
    protected function determineStatus(int $exitCode, array $parsed): string
    {
        if ($exitCode !== 0) {
            return 'failed';
        }

        if ($parsed['failed'] > 0) {
            return 'failed';
        }

        if ($parsed['passed'] > 0) {
            return 'passed';
        }

        return 'error';
    }

    /**
     * Extract failure details from output
     */
    protected function extractFailures(string $output): array
    {
        $failures = [];

        // Match FAIL lines and context
        if (preg_match_all('/FAIL\s+(.+?)(?=\n[A-Z]|\n\n[A-Z]|$)/s', $output, $matches)) {
            foreach ($matches[1] as $match) {
                $lines = explode("\n", trim($match));
                $testName = $lines[0] ?? 'Unknown';
                $failures[] = [
                    'test' => $testName,
                    'details' => implode("\n", array_slice($lines, 1, 10)), // First 10 lines of details
                ];
            }
        }

        return $failures;
    }

    /**
     * Extract error messages from output
     */
    protected function extractErrors(string $output): array
    {
        $errors = [];

        // Match error patterns
        if (preg_match_all('/(Error|Exception|Fatal error):\s*(.+?)(?=\n\n|\n[A-Z]|$)/s', $output, $matches)) {
            foreach ($matches[0] as $match) {
                $errors[] = substr($match, 0, 500); // Limit length
            }
        }

        return $errors;
    }

    /**
     * Get current git branch
     */
    protected function getGitBranch(): ?string
    {
        try {
            exec('git rev-parse --abbrev-ref HEAD 2>/dev/null', $output);

            return $output[0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get current git commit hash
     */
    protected function getGitCommit(): ?string
    {
        try {
            exec('git rev-parse --short HEAD 2>/dev/null', $output);

            return $output[0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get test history statistics
     */
    public function getStatistics(int $days = 30): array
    {
        $since = now()->subDays($days);

        $runs = TestResult::where('created_at', '>=', $since)->get();

        return [
            'total_runs' => $runs->count(),
            'passed_runs' => $runs->where('status', 'passed')->count(),
            'failed_runs' => $runs->where('status', 'failed')->count(),
            'avg_duration' => $runs->avg('duration') ?? 0,
            'avg_pass_rate' => $runs->avg('pass_rate') ?? 0,
            'trend' => $this->calculateTrend($runs),
        ];
    }

    /**
     * Calculate pass rate trend
     */
    protected function calculateTrend($runs): string
    {
        if ($runs->count() < 10) {
            return 'insufficient_data';
        }

        $recent = $runs->take(5)->avg('pass_rate') ?? 0;
        $older = $runs->slice(5, 5)->avg('pass_rate') ?? 0;

        if ($recent > $older + 5) {
            return 'improving';
        } elseif ($recent < $older - 5) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Get latest test run for each suite
     */
    public function getLatestBySuite(): array
    {
        $suites = ['full', 'Navigation', 'Transaction', 'User', 'Branch', 'Api', 'Compliance', 'Accounting'];
        $results = [];

        foreach ($suites as $suite) {
            $results[$suite] = TestResult::suite($suite)->latest()->first();
        }

        return $results;
    }
}
