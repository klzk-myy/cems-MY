<?php

namespace App\Http\Controllers\Report;

use App\Enums\ReportType;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
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
use App\Services\Reporting\TransactionReportQuery;
use App\Services\System\MathService;
use App\ValueObjects\Quarter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegulatoryReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportingService $reportingService,
        protected MathService $mathService,
    ) {}

    public function msb2(Msb2ReportRequest $request): View
    {
        $this->requireManagerOrAdmin();

        $date = $request->validated('date', now()->subDay()->toDateString());

        // Check existing report
        $reportGenerated = ReportGenerated::where('report_type', ReportType::Msb2)
            ->whereDate('period_start', $date)
            ->first();

        $rows = app(TransactionReportQuery::class)
            ->buySellSummary(
                Transaction::completed()->forDateRange($date, $date)->select('currency_code'),
                'currency_code',
                'amount_foreign',
                'amount_local'
            );

        $summary = $rows->mapWithKeys(function ($row) {
            $buyVolume = (string) $row->buy_volume;
            $sellVolume = (string) $row->sell_volume;

            return [
                $row->currency_code => [
                    'buy_count' => (int) $row->buy_count,
                    'buy_volume' => $buyVolume,
                    'buy_amount_myr' => (string) $row->buy_amount,
                    'sell_count' => (int) $row->sell_count,
                    'sell_volume' => $sellVolume,
                    'sell_amount_myr' => (string) $row->sell_amount,
                    'net_volume' => $this->mathService->subtract($buyVolume, $sellVolume),
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

        $this->reportingService->recordGeneratedReport(
            ReportType::Msb2,
            Carbon::parse($date)->startOfDay(),
            Carbon::parse($date)->endOfDay()
        );

        return $this->successResponse($report, 'MSB(2) report generated successfully.');
    }

    public function generateMSB2(StoreMsb2ReportRequest $request): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $filepath = $this->reportingService->generateMSB2($request->validated('date'));

        return $this->successResponse([
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ], 'MSB(2) report generated.');
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

        $this->reportingService->recordGeneratedReport(
            ReportType::Lmca,
            Carbon::parse($month)->startOfMonth(),
            Carbon::parse($month)->endOfMonth()
        );

        return $this->successResponse([
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ], 'Form LMCA generated successfully.');
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
            return $this->notFoundResponse('Report not found. Generate the report first.');
        }

        $report->update([
            'status' => $validated['status'],
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return $this->successResponse([
            'status' => $report->status,
        ], 'Report status updated successfully.');
    }

    /**
     * Quarterly Large Value Report
     */
    public function quarterlyLvr(QuarterlyLvrRequest $request): View
    {
        $this->requireManagerOrAdmin();

        $quarter = $request->validated('quarter', now()->format('Y').'-Q'.(int) ceil((int) now()->format('n') / 3));

        $reportGenerated = ReportGenerated::where('report_type', ReportType::Qlvr)
            ->where('period_start', Quarter::fromString($quarter)->startDate())
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

        $this->reportingService->recordGeneratedReport(
            ReportType::Qlvr,
            Quarter::fromString($quarter)->startDate(),
            Quarter::fromString($quarter)->endDate()
        );

        return $this->successResponse([
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ], 'Quarterly Large Value Report generated successfully.');
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

        $this->reportingService->recordGeneratedReport(
            ReportType::Plr,
            now()->startOfDay(),
            now()->endOfDay()
        );

        return $this->successResponse([
            'filename' => basename($filepath),
            'download_url' => url('/reports/download/'.basename($filepath)),
        ], 'Position Limit Report generated successfully.');
    }
}
