<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DocumentStorageService;
use App\Services\ExportService;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
        protected ExportService $exportService,
        protected DocumentStorageService $documentStorageService
    ) {}

    /**
     * Download a generated report.
     */
    public function download(string $filename): JsonResponse
    {
        if (! auth()->user()->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Manager or Admin access required.',
            ], 403);
        }

        $filepath = "reports/{$filename}";

        if (! $this->documentStorageService->exists($filepath)) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'download_url' => url('/reports/download/'.$filename),
        ]);
    }

    /**
     * Export report data.
     */
    public function export(Request $request): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'message' => 'Report exported successfully.',
            'download_url' => url('/reports/download/'.$filename),
        ]);
    }
}
