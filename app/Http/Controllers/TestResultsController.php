<?php

namespace App\Http\Controllers;

use App\Enums\TestResultStatus;
use App\Models\TestResult;
use App\Services\System\TestRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TestResultsController extends Controller
{
    public function __construct(
        protected TestRunnerService $testRunner
    ) {}

    /**
     * Display list of all test runs
     */
    public function index(Request $request): View
    {
        $query = TestResult::latest();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by suite
        if ($request->has('suite') && $request->suite !== 'all') {
            $query->where('test_suite', $request->suite);
        }

        $testRuns = $query->paginate(20);
        $statistics = $this->testRunner->getStatistics(30);
        $suites = TestResult::select('test_suite')->distinct()->pluck('test_suite');

        return view('test-results.index', compact('testRuns', 'statistics', 'suites'));
    }

    /**
     * Display detailed view of a test run
     */
    public function show(TestResult $testResult): View
    {
        $previousRun = TestResult::where('test_suite', $testResult->test_suite)
            ->where('id', '<', $testResult->id)
            ->orderBy('id', 'desc')
            ->first();

        return view('test-results.show', compact('testResult', 'previousRun'));
    }

    /**
     * Run tests and save results
     */
    public function run(Request $request): RedirectResponse
    {
        $suite = $request->input('suite', 'full');
        $options = $request->input('options', []);

        // Run tests asynchronously in background for long-running suites
        if ($suite === 'full') {
            // For full suite, we'll run it and save results
            $result = $this->testRunner->runTests($suite, $options);

            return redirect()
                ->route('test-results.show', $result)
                ->with('success', 'Test run completed successfully');
        }

        // For specific suites, run synchronously
        $result = $this->testRunner->runTests($suite, $options);

        return redirect()
            ->route('test-results.show', $result)
            ->with('success', "{$suite} tests completed");
    }

    /**
     * Display test statistics dashboard
     */
    public function statistics(Request $request): View
    {
        $days = $request->input('days', 30);
        $since = now()->subDays($days);
        $runs = TestResult::where('created_at', '>=', $since)->get();

        $statistics = $this->buildStatisticsSummary($runs);
        $latestBySuite = $this->buildLatestBySuite($since);
        $trendData = $this->buildTrendData($runs);

        return view('test-results.statistics', compact('statistics', 'latestBySuite', 'trendData', 'days'));
    }

    /**
     * Build summary statistics for the dashboard view.
     *
     * @param  Collection<int, TestResult>  $runs
     * @return array<string, mixed>
     */
    private function buildStatisticsSummary(Collection $runs): array
    {
        return [
            'total_runs' => $runs->count(),
            'total_tests' => (int) $runs->sum('total_tests'),
            'overall_pass_rate' => round($runs->avg('pass_rate') ?? 0, 2),
            'avg_duration' => round($runs->avg('duration') ?? 0, 2),
            'by_status' => $runs->groupBy(fn (TestResult $run) => $run->status->value)
                ->map(fn (Collection $group) => $group->count())
                ->toArray(),
            'daily_summary' => $runs->groupBy(fn (TestResult $run) => $run->created_at->format('Y-m-d'))
                ->map(fn (Collection $group) => [
                    'date' => $group->first()->created_at->format('Y-m-d'),
                    'passed' => $group->where('status', TestResultStatus::Passed)->count(),
                    'failed' => $group->whereIn('status', [TestResultStatus::Failed, TestResultStatus::Error])->count(),
                    'pass_rate' => round($group->avg('pass_rate') ?? 0, 2),
                ])
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Build per-suite latest run data with trend direction.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildLatestBySuite(\DateTimeInterface $since): array
    {
        return collect($this->testRunner->getLatestBySuite())
            ->filter()
            ->map(function (TestResult $run) use ($since) {
                $previousRun = TestResult::where('test_suite', $run->test_suite)
                    ->where('id', '<', $run->id)
                    ->where('created_at', '>=', $since)
                    ->latest()
                    ->first();

                return [
                    'last_run' => $run,
                    'pass_rate' => $run->pass_rate,
                    'trend' => $this->calculateRunTrend($run, $previousRun),
                ];
            })
            ->toArray();
    }

    /**
     * Build daily trend data for the pass rate chart.
     *
     * @param  Collection<int, TestResult>  $runs
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTrendData(Collection $runs): Collection
    {
        return $runs
            ->sortBy('created_at')
            ->groupBy(fn (TestResult $item) => $item->created_at->format('Y-m-d'))
            ->map(fn (Collection $group) => [
                'date' => $group->first()->created_at->format('Y-m-d'),
                'pass_rate' => round($group->avg('pass_rate') ?? 0, 2),
                'total_runs' => $group->count(),
                'failed_count' => $group->where('status', TestResultStatus::Failed)->count(),
            ])
            ->values();
    }

    /**
     * Determine trend direction compared to a previous run.
     */
    private function calculateRunTrend(TestResult $current, ?TestResult $previous): string
    {
        if (! $previous) {
            return 'stable';
        }

        if ($current->pass_rate > $previous->pass_rate + 1) {
            return 'up';
        }

        if ($current->pass_rate < $previous->pass_rate - 1) {
            return 'down';
        }

        return 'stable';
    }

    /**
     * Compare two test runs
     */
    public function compare(Request $request): View
    {
        $run1 = TestResult::findOrFail($request->input('run1'));
        $run2 = TestResult::findOrFail($request->input('run2'));

        return view('test-results.compare', compact('run1', 'run2'));
    }

    /**
     * Delete old test results
     */
    public function cleanup(Request $request): RedirectResponse
    {
        $days = $request->input('days', 90);

        $deleted = TestResult::where('created_at', '<', now()->subDays($days))->delete();

        return redirect()
            ->route('test-results.index')
            ->with('success', "Cleaned up {$deleted} old test results");
    }

    /**
     * Get test output as JSON (for AJAX requests)
     */
    public function output(TestResult $testResult): JsonResponse
    {
        return response()->json([
            'output' => $testResult->output,
            'failures' => $testResult->failures,
            'errors' => $testResult->errors,
        ]);
    }

    /**
     * Get latest test status (for dashboard widget)
     */
    public function latestStatus(): JsonResponse
    {
        $latest = TestResult::latest()->first();

        return response()->json([
            'status' => $latest?->status ?? 'unknown',
            'pass_rate' => $latest?->pass_rate ?? 0,
            'total_tests' => $latest?->total_tests ?? 0,
            'last_run' => $latest?->created_at?->diffForHumans() ?? 'Never',
        ]);
    }
}
