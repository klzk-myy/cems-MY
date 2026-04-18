<?php

namespace App\Http\Controllers;

use App\Models\TestResult;
use App\Services\TestRunnerService;
use Illuminate\Http\Request;

class TestResultsController extends Controller
{
    protected TestRunnerService $testRunner;

    public function __construct(TestRunnerService $testRunner)
    {
        $this->testRunner = $testRunner;
    }

    /**
     * Display list of all test runs
     */
    public function index(Request $request)
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
    public function show(TestResult $testResult)
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
    public function run(Request $request)
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
    public function statistics(Request $request)
    {
        $days = $request->input('days', 30);
        $statistics = $this->testRunner->getStatistics($days);
        $latestBySuite = $this->testRunner->getLatestBySuite();

        // Get trend data for chart
        $trendData = TestResult::where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($group) {
                return [
                    'date' => $group->first()->created_at->format('Y-m-d'),
                    'avg_pass_rate' => round($group->avg('pass_rate'), 2),
                    'total_runs' => $group->count(),
                    'failed_count' => $group->where('status', 'failed')->count(),
                ];
            })
            ->values();

        return view('test-results.statistics', compact('statistics', 'latestBySuite', 'trendData', 'days'));
    }

    /**
     * Compare two test runs
     */
    public function compare(Request $request)
    {
        $run1 = TestResult::findOrFail($request->input('run1'));
        $run2 = TestResult::findOrFail($request->input('run2'));

        return view('test-results.compare', compact('run1', 'run2'));
    }

    /**
     * Delete old test results
     */
    public function cleanup(Request $request)
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
    public function output(TestResult $testResult)
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
    public function latestStatus()
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
