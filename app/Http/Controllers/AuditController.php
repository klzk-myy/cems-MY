<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Services\AuditService;
use App\Services\LogRotationService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    protected AuditService $auditService;

    protected LogRotationService $logRotationService;

    public function __construct(AuditService $auditService, LogRotationService $logRotationService)
    {
        $this->auditService = $auditService;
        $this->logRotationService = $logRotationService;
    }

    /**
     * Display audit dashboard with statistics
     */
    public function dashboard()
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        // Get statistics
        $stats = [
            'total_logs' => SystemLog::count(),
            'today_logs' => SystemLog::whereDate('created_at', today())->count(),
            'critical_logs' => SystemLog::where('severity', 'CRITICAL')->count(),
            'error_logs' => SystemLog::where('severity', 'ERROR')->count(),
        ];

        // Severity distribution
        $severityCounts = SystemLog::selectRaw('COALESCE(severity, "INFO") as severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $severityColors = [
            'INFO' => 'info',
            'WARNING' => 'warning',
            'ERROR' => 'danger',
            'CRITICAL' => 'dark',
        ];

        // Top actions
        $topActions = SystemLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'action');

        // Recent activity
        $recentLogs = SystemLog::with('user')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Archive statistics
        $archiveStats = $this->logRotationService->getArchiveStats();

        return view('audit.dashboard', compact(
            'stats',
            'severityCounts',
            'severityColors',
            'topActions',
            'recentLogs',
            'archiveStats'
        ));
    }

    /**
     * Rotate old logs
     */
    public function rotate()
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $result = $this->logRotationService->archiveOldLogs();

        return redirect()->route('audit.dashboard')
            ->with('success', $result['message']);
    }

    /**
     * Display audit log index with filters
     */
    public function index(Request $request)
    {
        // Only managers and admins can access
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $filters = $request->only(['date_from', 'date_to', 'user_id', 'action', 'severity']);

        $query = SystemLog::with('user')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->action.'%');
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get unique actions for filter dropdown
        $actions = SystemLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Get users for filter dropdown
        $users = \App\Models\User::select('id', 'username')
            ->orderBy('username')
            ->get();

        // Severity options
        $severities = ['INFO', 'WARNING', 'ERROR', 'CRITICAL'];

        return view('audit.index', compact('logs', 'actions', 'users', 'severities', 'filters'));
    }

    /**
     * Show detailed view of a single log entry
     */
    public function show($logId)
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $log = SystemLog::with('user')->findOrFail($logId);

        return response()->json([
            'log' => $log,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
        ]);
    }

    /**
     * Export audit log to CSV or PDF
     */
    public function export(Request $request)
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'format' => 'required|in:CSV,PDF',
        ]);

        $format = $validated['format'];

        $logs = SystemLog::with('user')
            ->whereDate('created_at', '>=', $validated['date_from'])
            ->whereDate('created_at', '<=', $validated['date_to'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($format === 'CSV') {
            return $this->exportToCsv($logs, $validated['date_from'], $validated['date_to']);
        }

        return $this->exportToPdf($logs, $validated['date_from'], $validated['date_to']);
    }

    /**
     * Export to CSV format
     */
    protected function exportToCsv($logs, string $dateFrom, string $dateTo)
    {
        $filename = "audit_log_{$dateFrom}_to_{$dateTo}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'Timestamp',
                'User',
                'Action',
                'Entity Type',
                'Entity ID',
                'Severity',
                'IP Address',
                'Old Values',
                'New Values',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user->username ?? 'System',
                    $log->action,
                    $log->entity_type ?? 'N/A',
                    $log->entity_id ?? 'N/A',
                    $log->severity ?? 'INFO',
                    $log->ip_address,
                    json_encode($log->old_values ?? []),
                    json_encode($log->new_values ?? []),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to PDF format
     */
    protected function exportToPdf($logs, string $dateFrom, string $dateTo)
    {
        $filename = "audit_log_{$dateFrom}_to_{$dateTo}.pdf";

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('audit.pdf', compact('logs', 'dateFrom', 'dateTo'));

        return $pdf->download($filename);
    }
}
