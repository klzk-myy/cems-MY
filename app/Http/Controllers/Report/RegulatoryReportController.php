<?php

namespace App\Http\Controllers\Report;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\ReportGenerated;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegulatoryReportController extends Controller
{
    protected ReportingService $reportingService;

    protected MathService $mathService;

    public function __construct(
        ReportingService $reportingService,
        MathService $mathService
    ) {
        $this->reportingService = $reportingService;
        $this->mathService = $mathService;
    }

    protected function getQuarterStart(string $quarter): Carbon
    {
        $parts = explode('-', $quarter);
        $year = (int) $parts[0];
        $q = (int) substr($parts[1], 1);
        $startMonth = (($q - 1) * 3) + 1;

        return Carbon::create($year, $startMonth, 1)->startOfMonth();
    }

    protected function getQuarterEnd(string $quarter): Carbon
    {
        return $this->getQuarterStart($quarter)->copy()->addMonths(3)->subDay()->endOfDay();
    }

    public function lctr(Request $request)
    {
        $this->requireManagerOrAdmin();

        // Validate month parameter
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');

        // Check if report already generated
        $reportGenerated = ReportGenerated::where('report_type', 'LCTR')
            ->where('period_start', now()->parse($month)->startOfMonth())
            ->first();

        // Get qualifying transactions (>= RM50,000 and Completed per BNM requirements)
        $startDate = now()->parse($month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $transactions = Transaction::where('amount_local', '>=', ReportingService::CTR_THRESHOLD)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'user'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Count pending approval transactions that would qualify
        $pendingTransactions = Transaction::where('amount_local', '>=', ReportingService::CTR_THRESHOLD)
            ->where('status', TransactionStatus::PendingApproval)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $stats = [
            'count' => $transactions->count(),
            'total_amount' => $this->mathService->add('0', (string) $transactions->sum('amount_local')),
            'unique_customers' => $transactions->pluck('customer_id')->unique()->count(),
        ];

        return view('reports.lctr', compact('month', 'transactions', 'stats', 'reportGenerated', 'pendingTransactions'));
    }

    public function lctrGenerate(Request $request)
    {
        $this->requireManagerOrAdmin();

        $month = $request->input('month', now()->format('Y-m'));
        $report = $this->reportingService->generateLCTRData($month);

        $periodStart = now()->parse($month)->startOfMonth();
        $version = ReportGenerated::where('report_type', 'LCTR')
            ->where('period_start', $periodStart)
            ->max('version') + 1;

        ReportGenerated::create([
            'report_type' => 'LCTR',
            'period_start' => $periodStart,
            'period_end' => now()->parse($month)->endOfMonth(),
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
            'version' => $version,
        ]);

        return response()->json($report);
    }

    public function generateLCTR(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $filepath = $this->reportingService->generateLCTR($request->month);

        return response()->json([
            'message' => 'LCTR report generated',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }

    public function updateLCTRStatus(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'status' => 'required|in:Submitted',
        ]);

        $report = ReportGenerated::where('report_type', 'LCTR')
            ->where('period_start', now()->parse($validated['month'])->startOfMonth())
            ->first();

        if (! $report) {
            return response()->json([
                'message' => 'Report not found. Generate the report first.',
            ], 404);
        }

        $report->update([
            'status' => $validated['status'],
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Report status updated successfully',
            'status' => $report->status,
        ]);
    }

    public function msb2(Request $request)
    {
        $this->requireManagerOrAdmin();

        // Validate date parameter
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $date = $validated['date'] ?? now()->subDay()->toDateString();

        // Check existing report
        $reportGenerated = ReportGenerated::where('report_type', 'MSB2')
            ->whereDate('period_start', $date)
            ->first();

        // Get summary data using query builder
        $summary = DB::table('transactions')
            ->select(
                'currency_code',
                DB::raw("SUM(CASE WHEN type = '".TransactionType::Buy->value."' THEN amount_foreign ELSE 0 END) as buy_volume_foreign"),
                DB::raw("SUM(CASE WHEN type = '".TransactionType::Buy->value."' THEN amount_local ELSE 0 END) as buy_amount_myr"),
                DB::raw("COUNT(CASE WHEN type = '".TransactionType::Buy->value."' THEN 1 END) as buy_count"),
                DB::raw("SUM(CASE WHEN type = '".TransactionType::Sell->value."' THEN amount_foreign ELSE 0 END) as sell_volume_foreign"),
                DB::raw("SUM(CASE WHEN type = '".TransactionType::Sell->value."' THEN amount_local ELSE 0 END) as sell_amount_myr"),
                DB::raw("COUNT(CASE WHEN type = '".TransactionType::Sell->value."' THEN 1 END) as sell_count")
            )
            ->whereDate('created_at', $date)
            ->where('status', TransactionStatus::Completed)
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();

        // Calculate totals using MathService for precision
        $stats = [
            'total_transactions' => $this->mathService->add(
                (string) $summary->sum('buy_count'),
                (string) $summary->sum('sell_count')
            ),
            'total_buy_myr' => $this->mathService->add('0', (string) $summary->sum('buy_amount_myr')),
            'total_sell_myr' => $this->mathService->add('0', (string) $summary->sum('sell_amount_myr')),
            'net_position' => $this->mathService->subtract(
                $this->mathService->add('0', (string) $summary->sum('buy_amount_myr')),
                $this->mathService->add('0', (string) $summary->sum('sell_amount_myr'))
            ),
        ];

        // Calculate next business day
        $nextBusinessDay = now()->parse($date)->addWeekday()->format('Y-m-d');
        $isToday = $date === now()->toDateString();

        return view('reports.msb2.index', compact('date', 'summary', 'stats', 'reportGenerated', 'nextBusinessDay', 'isToday'));
    }

    public function msb2Generate(Request $request)
    {
        $this->requireManagerOrAdmin();

        $date = $request->input('date', now()->subDay()->toDateString());
        $report = $this->reportingService->generateMSB2Data($date);

        ReportGenerated::create([
            'report_type' => 'MSB2',
            'period_start' => $date,
            'period_end' => $date,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
        ]);

        return response()->json($report);
    }

    public function generateMSB2(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $filepath = $this->reportingService->generateMSB2($request->date);

        return response()->json([
            'message' => 'MSB(2) report generated',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }

    public function updateMSB2Status(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'status' => 'required|in:Submitted',
        ]);

        $report = ReportGenerated::where('report_type', 'MSB2')
            ->whereDate('period_start', $validated['date'])
            ->first();

        if (! $report) {
            return response()->json([
                'message' => 'Report not found. Generate the report first.',
            ], 404);
        }

        $report->update([
            'status' => $validated['status'],
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Report status updated successfully',
            'status' => $report->status,
        ]);
    }

    /**
     * BNM Form LMCA - Monthly regulatory report
     */
    public function lmca(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');

        $reportGenerated = ReportGenerated::where('report_type', 'LMCA')
            ->where('period_start', now()->parse($month)->startOfMonth())
            ->first();

        $reportData = $this->reportingService->generateFormLMCA($month);

        return view('reports.lmca', compact('month', 'reportData', 'reportGenerated'));
    }

    /**
     * Generate BNM Form LMCA CSV
     */
    public function lmcaGenerate(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = $validated['month'];
        $filepath = $this->reportingService->generateFormLMCACsv($month);

        ReportGenerated::create([
            'report_type' => 'LMCA',
            'period_start' => now()->parse($month)->startOfMonth(),
            'period_end' => now()->parse($month)->endOfMonth(),
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
        ]);

        return response()->json([
            'message' => 'Form LMCA generated successfully',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }

    /**
     * Update LMCA report status (mark as submitted)
     */
    public function updateLMCAStatus(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'status' => 'required|in:Submitted',
        ]);

        $report = ReportGenerated::where('report_type', 'LMCA')
            ->where('period_start', now()->parse($validated['month'])->startOfMonth())
            ->first();

        if (! $report) {
            return response()->json([
                'message' => 'Report not found. Generate the report first.',
            ], 404);
        }

        $report->update([
            'status' => $validated['status'],
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Report status updated successfully',
            'status' => $report->status,
        ]);
    }

    /**
     * Quarterly Large Value Report
     */
    public function quarterlyLvr(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'quarter' => 'nullable|date_format:Y-q',
        ]);

        $quarter = $validated['quarter'] ?? now()->format('Y').'-Q'.(int) ceil((int) now()->format('n') / 3);

        $reportGenerated = ReportGenerated::where('report_type', 'QLVR')
            ->where('period_start', $this->getQuarterStart($quarter))
            ->first();

        $reportData = $this->reportingService->generateQuarterlyLargeValueReport($quarter);

        return view('reports.quarterly-lvr', compact('quarter', 'reportData', 'reportGenerated'));
    }

    /**
     * Generate Quarterly Large Value Report CSV
     */
    public function quarterlyLvrGenerate(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'quarter' => 'required|date_format:Y-q',
        ]);

        $quarter = $validated['quarter'];
        $filepath = $this->reportingService->generateQuarterlyLargeValueCsv($quarter);

        ReportGenerated::create([
            'report_type' => 'QLVR',
            'period_start' => $this->getQuarterStart($quarter),
            'period_end' => $this->getQuarterEnd($quarter),
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
        ]);

        return response()->json([
            'message' => 'Quarterly Large Value Report generated successfully',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }

    /**
     * Position Limit Report
     */
    public function positionLimit(Request $request)
    {
        $this->requireManagerOrAdmin();

        $reportGenerated = ReportGenerated::where('report_type', 'PLR')
            ->whereDate('period_start', now()->toDateString())
            ->first();

        $reportData = $this->reportingService->generatePositionLimitReport();

        return view('reports.position-limit', compact('reportData', 'reportGenerated'));
    }

    /**
     * Generate Position Limit Report CSV
     */
    public function positionLimitGenerate(Request $request)
    {
        $this->requireManagerOrAdmin();

        $filepath = $this->reportingService->generatePositionLimitCsv();

        ReportGenerated::create([
            'report_type' => 'PLR',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
        ]);

        return response()->json([
            'message' => 'Position Limit Report generated successfully',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }
}
