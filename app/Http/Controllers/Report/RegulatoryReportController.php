<?php

namespace App\Http\Controllers\Report;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LmcaGenerateRequest;
use App\Http\Requests\LmcaReportRequest;
use App\Http\Requests\Msb2ReportRequest;
use App\Http\Requests\QuarterlyLvrGenerateRequest;
use App\Http\Requests\QuarterlyLvrRequest;
use App\Http\Requests\StoreMsb2ReportRequest;
use App\Http\Requests\UpdateReportStatusRequest;
use App\Models\ReportGenerated;
use App\Models\Transaction;
use App\Services\Reporting\ReportingService;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegulatoryReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
        protected MathService $mathService,
    ) {}

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

    public function msb2(Msb2ReportRequest $request): View
    {
        $this->requireManagerOrAdmin();

        $date = $request->validated('date', now()->subDay()->toDateString());

        // Check existing report
        $reportGenerated = ReportGenerated::where('report_type', ReportType::Msb2)
            ->whereDate('period_start', $date)
            ->first();

        $buyTransactions = Transaction::completed()
            ->forDateRange($date, $date)
            ->buy()
            ->get(['currency_code', 'amount_foreign', 'amount_local']);

        $sellTransactions = Transaction::completed()
            ->forDateRange($date, $date)
            ->sell()
            ->get(['currency_code', 'amount_foreign', 'amount_local']);

        $buySummary = $buyTransactions->groupBy('currency_code')->map(fn ($items) => [
            'count' => $items->count(),
            'volume' => (string) $items->sum('amount_foreign'),
            'amount_myr' => (string) $items->sum('amount_local'),
        ]);

        $sellSummary = $sellTransactions->groupBy('currency_code')->map(fn ($items) => [
            'count' => $items->count(),
            'volume' => (string) $items->sum('amount_foreign'),
            'amount_myr' => (string) $items->sum('amount_local'),
        ]);

        $currencies = $buySummary->keys()->merge($sellSummary->keys())->unique()->sort()->values();

        $summary = $currencies->mapWithKeys(function (string $currency) use ($buySummary, $sellSummary) {
            $buy = $buySummary->get($currency, ['count' => 0, 'volume' => '0', 'amount_myr' => '0']);
            $sell = $sellSummary->get($currency, ['count' => 0, 'volume' => '0', 'amount_myr' => '0']);

            return [
                $currency => [
                    'buy_count' => $buy['count'],
                    'buy_volume' => $buy['volume'],
                    'buy_amount_myr' => $buy['amount_myr'],
                    'sell_count' => $sell['count'],
                    'sell_volume' => $sell['volume'],
                    'sell_amount_myr' => $sell['amount_myr'],
                    'net_volume' => $this->mathService->subtract($buy['volume'], $sell['volume']),
                ],
            ];
        });

        $totalBuyMyr = (string) $summary->sum(function ($row) {
            return $row['buy_amount_myr'];
        });
        $totalSellMyr = (string) $summary->sum(function ($row) {
            return $row['sell_amount_myr'];
        });
        $totalTransactions = $summary->sum(function ($row) {
            return $row['buy_count'] + $row['sell_count'];
        });
        $totalVolume = $this->mathService->add($totalBuyMyr, $totalSellMyr);

        // Calculate totals using MathService for precision
        $stats = [
            'total_transactions' => (int) $totalTransactions,
            'total_buy_volume' => $totalBuyMyr,
            'total_sell_volume' => $totalSellMyr,
            'net_position' => $this->mathService->subtract($totalBuyMyr, $totalSellMyr),
            'avg_transaction_value' => $this->mathService->compare((string) $totalTransactions, '0') > 0
                ? $this->mathService->divide($totalVolume, (string) $totalTransactions)
                : '0',
        ];

        // Calculate next business day
        $nextBusinessDay = Carbon::parse($date)->addWeekday()->format('Y-m-d');
        $isToday = $date === now()->toDateString();

        return view('reports.msb2.index', compact('date', 'summary', 'stats', 'reportGenerated', 'nextBusinessDay', 'isToday'));
    }

    public function msb2Generate(Request $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $date = $request->input('date', now()->subDay()->toDateString());
        $report = $this->reportingService->generateMSB2Data($date);

        ReportGenerated::create([
            'report_type' => ReportType::Msb2,
            'period_start' => $date,
            'period_end' => $date,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'file_format' => 'CSV',
        ]);

        return response()->json($report);
    }

    public function generateMSB2(StoreMsb2ReportRequest $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $filepath = $this->reportingService->generateMSB2($request->validated('date'));

        return response()->json([
            'message' => 'MSB(2) report generated',
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ]);
    }

    public function updateMSB2Status(UpdateReportStatusRequest $request): JsonResponse
    {
        return $this->updateReportStatus(ReportType::Msb2, $request);
    }

    /**
     * BNM Form LMCA - Monthly regulatory report
     */
    public function lmca(LmcaReportRequest $request): View
    {
        $this->requireManagerOrAdmin();

        $month = $request->validated('month', now()->format('Y-m'));

        $reportGenerated = ReportGenerated::where('report_type', ReportType::Lmca)
            ->where('period_start', Carbon::parse($month)->startOfMonth())
            ->first();

        $reportData = $this->reportingService->generateFormLMCA($month);

        return view('reports.lmca', compact('month', 'reportData', 'reportGenerated'));
    }

    /**
     * Generate BNM Form LMCA CSV
     */
    public function lmcaGenerate(LmcaGenerateRequest $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $month = $request->validated('month');
        $filepath = $this->reportingService->generateFormLMCACsv($month);

        ReportGenerated::create([
            'report_type' => ReportType::Lmca,
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
    public function updateLMCAStatus(UpdateReportStatusRequest $request): JsonResponse
    {
        return $this->updateReportStatus(ReportType::Lmca, $request);
    }

    private function updateReportStatus(ReportType $reportType, UpdateReportStatusRequest $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        if ($reportType === ReportType::Msb2) {
            $periodStart = Carbon::parse($validated['date'])->startOfDay();
        } else {
            $periodStart = Carbon::parse($validated['month'])->startOfMonth();
        }

        $report = ReportGenerated::where('report_type', $reportType)
            ->where('period_start', $periodStart)
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
    public function quarterlyLvr(QuarterlyLvrRequest $request): View
    {
        $this->requireManagerOrAdmin();

        $quarter = $request->validated('quarter', now()->format('Y').'-Q'.(int) ceil((int) now()->format('n') / 3));

        $reportGenerated = ReportGenerated::where('report_type', ReportType::Qlvr)
            ->where('period_start', $this->getQuarterStart($quarter))
            ->first();

        $reportData = $this->reportingService->generateQuarterlyLargeValueReport($quarter);

        return view('reports.quarterly-lvr', compact('quarter', 'reportData', 'reportGenerated'));
    }

    /**
     * Generate Quarterly Large Value Report CSV
     */
    public function quarterlyLvrGenerate(QuarterlyLvrGenerateRequest $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $quarter = $request->validated('quarter');
        $filepath = $this->reportingService->generateQuarterlyLargeValueCsv($quarter);

        ReportGenerated::create([
            'report_type' => ReportType::Qlvr,
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
    public function positionLimit(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $reportGenerated = ReportGenerated::where('report_type', ReportType::Plr)
            ->whereDate('period_start', now()->toDateString())
            ->first();

        $reportData = $this->reportingService->generatePositionLimitReport();

        return view('reports.position-limit', compact('reportData', 'reportGenerated'));
    }

    /**
     * Generate Position Limit Report CSV
     */
    public function positionLimitGenerate(Request $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $filepath = $this->reportingService->generatePositionLimitCsv();

        ReportGenerated::create([
            'report_type' => ReportType::Plr,
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
