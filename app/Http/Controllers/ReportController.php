<?php

namespace App\Http\Controllers;

use App\Models\ReportGenerated;
use App\Services\DocumentStorageService;
use App\Services\ExportService;
use App\Services\ReportingService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected ReportingService $reportingService;

    protected ExportService $exportService;

    protected DocumentStorageService $documentStorageService;

    public function __construct(
        ReportingService $reportingService,
        ExportService $exportService,
        DocumentStorageService $documentStorageService
    ) {
        $this->reportingService = $reportingService;
        $this->exportService = $exportService;
        $this->documentStorageService = $documentStorageService;
    }

    public function download(string $filename)
    {
        $this->requireManagerOrAdmin();

        $filepath = "reports/{$filename}";

        if (! $this->documentStorageService->exists($filepath)) {
            abort(404, 'Report not found');
        }

        return $this->documentStorageService->download($filepath);
    }

    public function export(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'report_type' => 'required|in:lctr,msb2,trial_balance,pl,balance_sheet',
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ]);

        $data = match ($validated['report_type']) {
            'lctr' => $this->reportingService->generateLCTRData($validated['period']),
            'msb2' => $this->reportingService->generateMSB2Data($validated['period']),
            default => ['data' => []],
        };

        $filename = "{$validated['report_type']}_{$validated['period']}.".strtolower($validated['format']);

        switch ($validated['format']) {
            case 'CSV':
                $path = $this->exportService->toCSV($data['data'], $filename);

                return response()->download($path);

            case 'PDF':
                $path = $this->exportService->toPDF($data, 'reports.pdf', $filename);

                return response()->download($path);

            case 'XLSX':
                $path = $this->exportService->toExcel($data['data'], $filename);

                return response()->download($path);
        }
    }

    /**
     * Report history with version tracking
     */
    public function history(Request $request)
    {
        $this->requireManagerOrAdmin();

        $reportType = $request->input('type');
        $periodStart = $request->input('period');

        $query = ReportGenerated::with(['generatedBy', 'submittedBy'])
            ->orderBy('generated_at', 'desc');

        if ($reportType) {
            $query->where('report_type', $reportType);
        }

        if ($periodStart) {
            $query->where('period_start', $periodStart);
        }

        $reports = $query->paginate(20);

        $reportTypes = ReportGenerated::select('report_type')->distinct()->pluck('report_type');

        return view('reports.history', compact('reports', 'reportTypes', 'reportType', 'periodStart'));
    }

    /**
     * Compare two report versions
     */
    public function compare(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'report_type' => 'required|string',
            'period_start' => 'required|date',
            'version1' => 'required|integer',
            'version2' => 'required|integer',
        ]);

        $report1 = ReportGenerated::where('report_type', $validated['report_type'])
            ->where('period_start', $validated['period_start'])
            ->where('version', $validated['version1'])
            ->first();

        $report2 = ReportGenerated::where('report_type', $validated['report_type'])
            ->where('period_start', $validated['period_start'])
            ->where('version', $validated['version2'])
            ->first();

        if (! $report1 || ! $report2) {
            return back()->with('error', 'Report version not found');
        }

        return view('reports.compare', compact('report1', 'report2'));
    }
}
