<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\ReportGenerated;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Compliance\ComplianceFlagService;
use App\Services\System\CacheOptimizationService;
use App\Services\Transaction\RateApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CurrencyPositionService $currencyPositionService,
        protected RateApiService $rateApiService,
        protected CacheOptimizationService $cacheOptimizationService,
    ) {}

    /**
     * Display the dashboard with cached daily statistics.
     *
     * Statistics are cached to reduce database load and refreshed every minute.
     */
    public function index(): View
    {
        $stats = [
            'total_transactions' => $this->rememberDashboard(
                'transactions.total',
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::whereDate('created_at', today())->count();
                }
            ),
            'buy_volume' => $this->rememberDashboard(
                'transactions.buy_volume',
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::completed()->whereDate('created_at', today())->buy()->sum('amount_local');
                }
            ),
            'sell_volume' => $this->rememberDashboard(
                'transactions.sell_volume',
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::completed()->whereDate('created_at', today())->sell()->sum('amount_local');
                }
            ),
            'flagged' => $this->rememberDashboard(
                'compliance.flagged',
                ['dashboard', 'compliance'],
                function () {
                    return FlaggedTransaction::where('status', 'Open')->count();
                }
            ),
            'active_customers' => $this->rememberDashboard(
                'customers.active',
                ['dashboard', 'customers'],
                function () {
                    return Customer::count();
                }
            ),
        ];

        $recent_transactions = $this->rememberDashboard(
            'transactions.recent',
            ['dashboard', 'transactions'],
            function () {
                return Transaction::with('customer')
                    ->whereDate('created_at', today())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        );

        $this->cacheOptimizationService->putStats(now()->addSeconds(60));

        return view('pages.dashboard', compact('stats', 'recent_transactions'));
    }

    private function rememberDashboard(string $key, array $tags, callable $callback): mixed
    {
        return $this->cacheOptimizationService->remember("dashboard.{$key}", 60, $tags, $callback);
    }

    /**
     * Display the compliance dashboard.
     *
     * Only Compliance Officers and Admins can access this page.
     */
    public function compliance(Request $request): View
    {
        $this->authorize('viewAny', FlaggedTransaction::class);

        $query = FlaggedTransaction::with(['transaction.customer', 'assignedTo', 'reviewer']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('flag_type') && $request->flag_type !== 'all') {
            $query->where('flag_type', $request->flag_type);
        }

        $flags = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $counts = FlaggedTransaction::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $stats = [
            'open' => $counts->get('Open', 0),
            'under_review' => $counts->get('Under_Review', 0),
            'resolved_today' => FlaggedTransaction::where('status', 'Resolved')
                ->whereDate('resolved_at', today())
                ->count(),
            'high_priority' => FlaggedTransaction::whereIn('flag_type', ['Sanction_Match', 'Structuring', 'Velocity'])
                ->where('status', '!=', 'Resolved')
                ->count(),
        ];

        return view('pages.compliance.index', compact('flags', 'stats'));
    }

    /**
     * Assign a flagged transaction to the current user for review.
     *
     * Only Compliance Officers and Admins can assign flags.
     */
    public function assignFlag(Request $request, FlaggedTransaction $flaggedTransaction): RedirectResponse
    {
        $this->authorize('assign', $flaggedTransaction);

        app(ComplianceFlagService::class)->assignToCurrentUser($flaggedTransaction, auth()->user());

        return back()->with('success', 'Flag assigned to you for review.');
    }

    /**
     * Mark a flagged transaction as resolved.
     *
     * Only Compliance Officers and Admins can resolve flags.
     */
    public function resolveFlag(Request $request, FlaggedTransaction $flaggedTransaction): RedirectResponse
    {
        $this->authorize('resolve', $flaggedTransaction);

        app(ComplianceFlagService::class)->resolve($flaggedTransaction, auth()->user());

        return back()->with('success', 'Flag marked as resolved.');
    }

    /**
     * Display the accounting dashboard.
     *
     * Only Managers and Admins can access this page.
     */
    public function accounting(): View
    {
        $this->requireManagerOrAdmin();

        $positions = $this->currencyPositionService->getAllPositions();
        $totalPnl = $this->currencyPositionService->getTotalPnl();

        return view('pages.accounting.index', compact('positions', 'totalPnl'));
    }

    /**
     * Display the reports dashboard.
     *
     * Only Managers, Compliance Officers and Admins can access this page.
     */
    public function reports(): View
    {
        $this->authorize('viewReports');

        $recentReports = ReportGenerated::with('generatedBy')
            ->orderBy('generated_at', 'desc')
            ->limit(10)
            ->get();

        return view('pages.reports.index', compact('recentReports'));
    }

    /**
     * Get exchange rate history for Chart.js.
     */
    public function rateHistory(string $currencyCode): JsonResponse
    {
        $trend = $this->rateApiService->getRateTrend($currencyCode, 30);

        return response()->json([
            'currency' => $trend['currency'],
            'labels' => array_column($trend['data'], 'date'),
            'rates' => array_column($trend['data'], 'rate'),
            'trend' => $trend['trend'],
        ]);
    }
}
