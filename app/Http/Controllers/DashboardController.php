<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\ReportGenerated;
use App\Models\StrReport;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\CacheOptimizationService;
use App\Services\CacheTagsService;
use App\Services\CurrencyPositionService;
use App\Services\RateApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function index()
    {
        // Use caching for all statistics to reduce database load
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

        // Store cache statistics for testing/monitoring
        Cache::put('dashboard_cache_stats', $this->cacheOptimizationService->getStats(), now()->addSeconds(60));

        return view('dashboard', compact('stats', 'recent_transactions'));
    }

    public function compliance(Request $request)
    {
        // Only Compliance Officers and Admins can access
        if (! auth()->user()->isComplianceOfficer()) {
            abort(403, 'Unauthorized. Compliance Officer access required.');
        }

        // Build query with filters
        $query = FlaggedTransaction::with(['transaction.customer', 'assignedTo', 'reviewer']);

        // Apply status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Apply flag_type filter
        if ($request->filled('flag_type') && $request->flag_type !== 'all') {
            $query->where('flag_type', $request->flag_type);
        }

        // Get paginated flags
        $flags = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate STR stats including overdue tracking
        $strStats = [
            'draft' => StrReport::where('status', 'draft')->count(),
            'pending_review' => StrReport::where('status', 'pending_review')->count(),
            'pending_approval' => StrReport::where('status', 'pending_approval')->count(),
            'submitted' => StrReport::where('status', 'submitted')->count(),
            'overdue' => StrReport::whereNotNull('filing_deadline')
                ->where('filing_deadline', '<', now())
                ->whereIn('status', ['draft', 'pending_review', 'pending_approval'])
                ->count(),
            'near_deadline' => StrReport::whereNotNull('filing_deadline')
                ->whereBetween('filing_deadline', [now(), now()->addDays(2)])
                ->whereIn('status', ['draft', 'pending_review', 'pending_approval'])
                ->count(),
        ];

        // Calculate stats
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

        return view('compliance.index', compact('flags', 'stats', 'strStats'));
    }

    public function assignFlag(Request $request, FlaggedTransaction $flaggedTransaction)
    {
        if (! auth()->user()->isComplianceOfficer()) {
            abort(403);
        }

        $oldStatus = $flaggedTransaction->status;
        $oldAssignedTo = $flaggedTransaction->assigned_to;

        $flaggedTransaction->update([
            'assigned_to' => auth()->id(),
            'status' => 'Under_Review',
        ]);

        // Invalidate dashboard cache
        $this->cacheTagsService->invalidate('dashboard');

        // Audit log
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

    public function resolveFlag(Request $request, FlaggedTransaction $flaggedTransaction)
    {
        if (! auth()->user()->isComplianceOfficer()) {
            abort(403);
        }

        $oldStatus = $flaggedTransaction->status;

        $flaggedTransaction->update([
            'status' => 'Resolved',
            'reviewed_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        // Invalidate dashboard cache
        $this->cacheTagsService->invalidate('dashboard');

        // Audit log
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

    public function accounting()
    {
        // Only Managers and Admins can access
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $positions = $this->currencyPositionService->getAllPositions();
        $totalPnl = $this->currencyPositionService->getTotalPnl();

        return view('accounting.index', compact('positions', 'totalPnl'));
    }

    public function reports()
    {
        // Only Managers, Compliance Officers and Admins can access
        if (! auth()->user()->role->canViewReports()) {
            abort(403, 'Unauthorized. Manager or Compliance Officer access required.');
        }

        $recentReports = ReportGenerated::with('generatedBy')
            ->orderBy('generated_at', 'desc')
            ->limit(10)
            ->get();

        return view('reports', compact('recentReports'));
    }

    /**
     * Get exchange rate history for Chart.js
     */
    public function rateHistory(string $currencyCode)
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
