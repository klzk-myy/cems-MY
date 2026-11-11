<?php

namespace App\Http\Controllers\Bi;

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Concerns\ResolvesBiFilters;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiExportRequest;
use App\Services\Reporting\Generators\BiReportGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * On-demand CSV exports for the BI report pages. Generates through the
 * same BiReportGenerator the scheduled pipeline uses, so a downloaded
 * CSV and a scheduled one carry identical rows for the same period.
 */
class ExportController extends Controller
{
    use RequiresPermission;
    use ResolvesBiFilters;
    use ResolvesBranchScope;

    public function __construct(
        protected BiReportGenerator $generator,
    ) {}

    public function store(BiExportRequest $request): StreamedResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        [$from, $to] = $this->resolveBiPeriod($request);

        $branchId = $this->resolveReportBranchId($request->user(), $request);

        $type = match ((string) $request->input('type')) {
            'digest' => ReportType::BiDailyDigest,
            'branches' => ReportType::BiBranchPerformance,
            'currencies' => ReportType::BiCurrencyBreakdown,
            'tellers' => ReportType::BiTellerProductivity,
            'customers' => ReportType::BiTopCustomers,
            default => throw new \InvalidArgumentException('Unsupported export type'),
        };

        $filePath = $this->generator->generate($type, $from, $to, ['branch_id' => $branchId]);

        return Storage::download($filePath);
    }
}
