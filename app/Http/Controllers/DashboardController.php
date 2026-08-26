<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\ReportGenerated;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceFlagService;
use App\Services\EodReconciliationService;
use App\Services\System\CacheOptimizationService;
use App\Services\System\SystemAlertService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CacheOptimizationService $cacheOptimizationService,
        protected SystemAlertService $systemAlertService,
        protected ComplianceFlagService $complianceFlagService,
        protected EodReconciliationService $eodService,
    ) {}

    /**
     * Display the dashboard with cached daily statistics.
     *
     * Statistics are cached to reduce database load and refreshed every minute.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Non-admins only see their own branch's data. Admins see the consolidated view.
        $branchId = $user->role->canManageAllBranches() ? null : $user->branch_id;

        // The cache key must include the scope so one branch's cached numbers are
        // never served to another branch (cache-poisoning cross-branch leak).
        $scopeSuffix = $branchId ? "branch.{$branchId}" : 'all';

        $stats = [
            'total_transactions' => $this->rememberDashboard(
                "transactions.total.{$scopeSuffix}",
                ['dashboard', 'transactions'],
                function () use ($branchId) {
                    return Transaction::whereDate('created_at', today())
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->count();
                }
            ),
            'buy_volume' => $this->rememberDashboard(
                "transactions.buy_volume.{$scopeSuffix}",
                ['dashboard', 'transactions'],
                function () use ($branchId) {
                    return Transaction::completed()->whereDate('created_at', today())
                        ->buy()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->sum('amount_local');
                }
            ),
            'sell_volume' => $this->rememberDashboard(
                "transactions.sell_volume.{$scopeSuffix}",
                ['dashboard', 'transactions'],
                function () use ($branchId) {
                    return Transaction::completed()->whereDate('created_at', today())
                        ->sell()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->sum('amount_local');
                }
            ),
            // Open-compliance-flag count is compliance-sensitive: hide it from
            // tellers and managers, not just by branch.
            'flagged' => $user->isComplianceOfficer()
                ? $this->rememberDashboard(
                    "compliance.flagged.{$scopeSuffix}",
                    ['dashboard', 'compliance'],
                    fn () => FlaggedTransaction::where('status', 'Open')->count()
                )
                : 0,
            'active_customers' => $this->rememberDashboard(
                "customers.active.{$scopeSuffix}",
                ['dashboard', 'customers'],
                function () use ($branchId) {
                    return Customer::when($branchId, fn ($q) => $q->forBranch($branchId))->count();
                }
            ),
            // DLQ items are operations-sensitive and only actionable by admins.
            'dlq_count' => $user->isAdmin()
                ? $this->rememberDashboard(
                    "transactions.dlq.{$scopeSuffix}",
                    ['dashboard', 'transactions'],
                    fn () => Transaction::where('is_dlq', true)->count()
                )
                : 0,
        ];

        $recent_transactions = $this->rememberDashboard(
            "transactions.recent.{$scopeSuffix}",
            ['dashboard', 'transactions'],
            function () use ($branchId) {
                return Transaction::with('customer')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->whereDate('created_at', today())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        );

        // System monitoring widget - admin only. Combines the DLQ count with
        // unacknowledged system alerts so admins see operational attention
        // items at a glance. Empty for non-admins to avoid leaking ops data.
        $monitoring = $user->isAdmin()
            ? $this->buildMonitoringData($stats['dlq_count'])
            : null;

        $this->cacheOptimizationService->putStats(now()->addSeconds(60));

        return view('pages.dashboard', compact('stats', 'recent_transactions', 'monitoring'));
    }

    /**
     * Build the admin system-monitoring widget payload.
     *
     * The alert lookups are cached (60s, like the rest of the dashboard) so the
     * widget does not run five SystemAlert queries on every admin page load.
     *
     * @return array{dlq_count: int, alert_counts: array<string, int>, recent_alerts: array}
     */
    private function buildMonitoringData(int $dlqCount): array
    {
        $widget = $this->rememberDashboard(
            'system.alerts.widget',
            ['dashboard', 'system'],
            fn () => $this->systemAlertService->getDashboardWidgetData()
        );

        return [
            'dlq_count' => $dlqCount,
            'alert_counts' => $widget['counts'],
            'recent_alerts' => $widget['recent'],
        ];
    }

    private function rememberDashboard(string $key, array $tags, \Closure $callback): mixed
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

        $stats = $this->complianceFlagService->getStatusCounts();

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

        $this->complianceFlagService->assignToCurrentUser($flaggedTransaction, auth()->user());

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

        $this->complianceFlagService->resolve($flaggedTransaction, auth()->user());

        return back()->with('success', 'Flag marked as resolved.');
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
     * EOD Reconciliation dashboard for managers.
     */
    public function eod(Request $request): View
    {
        $user = auth()->user();
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : today();

        $branchId = ($user->isManager() && $user->branch_id) ? (int) $user->branch_id : null;

        $report = $this->eodService->generateDailyReconciliationSummary($date, $branchId);

        return view('reports.eod-dashboard', compact('report', 'date'));
    }
}
