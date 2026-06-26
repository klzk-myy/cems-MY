<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\ExportReportRequest;
use App\Services\Reporting\ReportingService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
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

        // Sanitize filename to prevent path traversal
        $filename = basename($filename);
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Invalid filename.');
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
    public function export(ExportReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reportType = ReportType::from($validated['report_type']);

        $data = match ($reportType) {
            ReportType::Msb2 => $this->reportingService->generateMSB2Data($validated['period']),
            default => ['data' => []],
        };

        $filename = "{$reportType->value}_{$validated['period']}.".strtolower($validated['format']);

        return response()->json([
            'success' => true,
            'message' => 'Report exported successfully.',
            'download_url' => url('/reports/download/'.$filename),
        ]);
    }
}
