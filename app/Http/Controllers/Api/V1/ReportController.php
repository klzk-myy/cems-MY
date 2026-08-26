<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\EnsuresManagerOrAdmin;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\System\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ApiResponse;
    use EnsuresManagerOrAdmin;

    /**
     * Maps normalized filename prefixes to ReportType values so downloads can
     * be audit-tagged with the regulatory report type they belong to.
     */
    private const FILENAME_TYPE_MAP = [
        'msb2' => 'msb2',
        'lmca' => 'lmca',
        'qlvr' => 'qlvr',
        'positionlimit' => 'plr',
        'plr' => 'plr',
        'trialbalance' => 'trial_balance',
        'monthend' => 'month_end',
        'pl' => 'profit_loss',
        'balancesheet' => 'balance_sheet',
    ];

    public function __construct(
        protected DocumentStorageService $documentStorageService,
        protected AuditService $auditService
    ) {}

    /**
     * Download a generated report.
     */
    public function download(string $filename): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        if ($response = $this->requireManagerOrAdminResponse()) {
            return $response;
        }

        // Sanitize filename to prevent path traversal. basename() strips any
        // directory component, so a relative "../../etc/passwd" becomes
        // "passwd" and is confined to the reports directory.
        $filename = basename($filename);

        $filepath = "reports/{$filename}";

        if (! $this->documentStorageService->exists($filepath)) {
            return $this->notFoundResponse('Report not found.');
        }

        $actor = request()->user();

        $this->auditService->logReportAccessEvent('report_downloaded', [
            'user_id' => auth()->id(),
            'actor' => $actor !== null ? $actor->username : 'guest',
            'entity_type' => 'Report',
            'new_values' => [
                'report_type' => $this->resolveReportTypeFromFilename($filename),
                'report_file' => $filename,
                'file_path' => $filepath,
            ],
        ]);

        return $this->documentStorageService->download($filepath);
    }

    /**
     * Best-effort report type extraction from the generated filename
     * (e.g. "MSB2_2026-08-25.csv" -> "msb2"); unknown prefixes return null.
     */
    protected function resolveReportTypeFromFilename(string $filename): ?string
    {
        $token = strtok($filename, '_-.');
        $prefix = strtolower(preg_replace('/[^A-Za-z]/', '', $token === false ? '' : $token));

        if ($prefix === '') {
            return null;
        }

        return self::FILENAME_TYPE_MAP[$prefix] ?? null;
    }
}
