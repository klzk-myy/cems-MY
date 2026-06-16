<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\ReportGenerated;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CacheOptimizationService;
use App\Services\CacheTagsService;
use App\Services\CurrencyPositionService;
use App\Services\RateApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected AuditService $auditService;

    protected CurrencyPositionService $currencyPositionService;

    protected RateApiService $rateApiService;

    protected CacheOptimizationService $cacheOptimizationService;

    protected CacheTagsService $cacheTagsService;

    public function __construct(
        AuditService $auditService,
        CurrencyPositionService $currencyPositionService,
        RateApiService $rateApiService,
        CacheOptimizationService $cacheOptimizationService,
        CacheTagsService $cacheTagsService
    ) {
        $this->auditService = $auditService;
        $this->currencyPositionService = $currencyPositionService;
        $this->rateApiService = $rateApiService;
        $this->cacheOptimizationService = $cacheOptimizationService;
        $this->cacheTagsService = $cacheTagsService;
    }

    /**
     * Display the dashboard with cached daily statistics.
     *
     * Statistics are cached to reduce database load and refreshed every minute.
     */
    public function index(): View
    {
        $stats = [
            'total_transactions' => $this->cacheOptimizationService->remember(
                'dashboard.transactions.total',
                60,
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::whereDate('created_at', today())->count();
                }
            ),
            'buy_volume' => $this->cacheOptimizationService->remember(
                'dashboard.transactions.buy_volume',
                60,
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::whereDate('created_at', today())->where('type', 'Buy')->sum('amount_local');
                }
            ),
            'sell_volume' => $this->cacheOptimizationService->remember(
                'dashboard.transactions.sell_volume',
                60,
                ['dashboard', 'transactions'],
                function () {
                    return Transaction::whereDate('created_at', today())->where('type', 'Sell')->sum('amount_local');
                }
            ),
            'flagged' => $this->cacheOptimizationService->remember(
                'dashboard.compliance.flagged',
                60,
                ['dashboard', 'compliance'],
                function () {
                    return FlaggedTransaction::where('status', 'Open')->count();
                }
            ),
            'active_customers' => $this->cacheOptimizationService->remember(
                'dashboard.customers.active',
                60,
                ['dashboard', 'customers'],
                function () {
                    return Customer::count();
                }
            ),
        ];

        $recent_transactions = $this->cacheOptimizationService->remember(
            'dashboard.transactions.recent',
            60,
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

    /**
     * Display the compliance dashboard.
     *
     * Only Compliance Officers and Admins can access this page.
     */
    public function compliance(Request $request): View
    {
        $this->ensureComplianceOfficerAccess(auth()->user(), 'Unauthorized. Compliance Officer access required.');

        $query = FlaggedTransaction::with(['transaction.customer', 'assignedTo', 'reviewer']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('flag_type') && $request->flag_type !== 'all') {
            $query->where('flag_type', $request->flag_type);
        }

        $flags = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $stats = [
            'open' => FlaggedTransaction::where('status', 'Open')->count(),
            'under_review' => FlaggedTransaction::where('status', 'Under_Review')->count(),
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
        $this->ensureComplianceOfficerAccess(auth()->user());

        $oldStatus = $flaggedTransaction->status;
        $oldAssignedTo = $flaggedTransaction->assigned_to;

        $flaggedTransaction->update([
            'assigned_to' => auth()->id(),
            'status' => 'Under_Review',
        ]);

        $this->cacheTagsService->invalidate('dashboard');

        $this->auditService->logWithSeverity(
            'compliance_flag_assigned',
            [
                'user_id' => auth()->id(),
                'entity_type' => 'FlaggedTransaction',
                'entity_id' => $flaggedTransaction->id,
                'old_values' => [
                    'status' => $oldStatus,
                    'assigned_to' => $oldAssignedTo,
                ],
                'new_values' => [
                    'status' => 'Under_Review',
                    'assigned_to' => auth()->id(),
                    'assigned_by' => auth()->user()->username,
                ],
            ],
            'WARNING'
        );

        return back()->with('success', 'Flag assigned to you for review.');
    }

    /**
     * Mark a flagged transaction as resolved.
     *
     * Only Compliance Officers and Admins can resolve flags.
     */
    public function resolveFlag(Request $request, FlaggedTransaction $flaggedTransaction): RedirectResponse
    {
        $this->ensureComplianceOfficerAccess(auth()->user());

        $oldStatus = $flaggedTransaction->status;

        $flaggedTransaction->update([
            'status' => 'Resolved',
            'reviewed_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $this->cacheTagsService->invalidate('dashboard');

        $this->auditService->logWithSeverity(
            'compliance_flag_resolved',
            [
                'user_id' => auth()->id(),
                'entity_type' => 'FlaggedTransaction',
                'entity_id' => $flaggedTransaction->id,
                'old_values' => [
                    'status' => $oldStatus,
                ],
                'new_values' => [
                    'status' => 'Resolved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_by_username' => auth()->user()->username,
                    'resolved_at' => now()->toDateTimeString(),
                ],
            ],
            'INFO'
        );

        return back()->with('success', 'Flag marked as resolved.');
    }

    /**
     * Display the accounting dashboard.
     *
     * Only Managers and Admins can access this page.
     */
    public function accounting(): View
    {
        $this->ensureManagerAccess(auth()->user());

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
        $this->ensureCanViewReports(auth()->user());

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

    /**
     * Ensure the user is a Compliance Officer or Admin.
     */
    private function ensureComplianceOfficerAccess(User $user, string $message = ''): void
    {
        if (! $user->isComplianceOfficer()) {
            abort(403, $message);
        }
    }

    /**
     * Ensure the user is a Manager or Admin.
     */
    private function ensureManagerAccess(User $user): void
    {
        if (! $user->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }
    }

    /**
     * Ensure the user is allowed to view reports.
     *
     * Managers, Compliance Officers, and Admins may view reports.
     */
    private function ensureCanViewReports(User $user): void
    {
        if (! $user->role->canViewReports()) {
            abort(403, 'Unauthorized. Manager or Compliance Officer access required.');
        }
    }
}
