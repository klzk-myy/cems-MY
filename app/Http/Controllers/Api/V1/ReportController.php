<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportType;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesManager;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\ExportReportRequest;
use App\Services\Reporting\ReportingService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    use ApiResponse;
    use AuthorizesManager;

    public function __construct(
        protected ReportingService $reportingService,
        protected DocumentStorageService $documentStorageService
    ) {}

    /**
     * Download a generated report.
     */
    public function download(string $filename): JsonResponse
    {
        if ($response = $this->requireManagerOrAdminResponse()) {
            return $response;
        }

        // Sanitize filename to prevent path traversal
        $filename = basename($filename);
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Invalid filename.');
        }

        $filepath = "reports/{$filename}";

        if (! $this->documentStorageService->exists($filepath)) {
            return $this->notFoundResponse('Report not found.');
        }

        return $this->successResponse(['download_url' => url('/reports/download/'.$filename)]);
    }

    /**
     * Export report data.
     */
    public function export(ExportReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reportType = ReportType::from($validated['report_type']);

        // Only MSB2 export is currently implemented. Other report types are
        // accepted for normalization purposes and return an empty data set.
        $data = match ($reportType) {
            ReportType::Msb2 => $this->reportingService->generateMSB2Data($validated['period']),
            default => ['data' => []],
        };

        $filename = "{$reportType->value}_{$validated['period']}.".strtolower($validated['format']);

        return $this->successResponse(['download_url' => url('/reports/download/'.$filename)], 'Report exported successfully.');
    }
}
