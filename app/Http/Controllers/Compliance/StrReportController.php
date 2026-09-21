<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\StrReportStatus;
use App\Exceptions\Domain\FileOperationException;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitStrReportRequest;
use App\Models\Compliance\StrReport;
use App\Services\AuditService;
use App\Services\Compliance\StrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StrReportController
 *
 * BNM Suspicious Transaction Report (pd-00 section 22) filing surface:
 * list/detail with status filters, draft creation from a closed case,
 * submission with the BNM reference, acknowledgement, and a CSV export in
 * BNM-style column order. Route group carries role:compliance,admin; the
 * StrReportPolicy (auto-discovered) re-enforces the same matrix.
 */
class StrReportController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected StrReportService $strReportService,
        protected AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StrReport::class);

        $query = StrReport::with(['customer', 'createdBy'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('customer')) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->get('customer'));
            $query->whereHas('customer', fn ($q) => $q->whereRaw('full_name like ? escape "\\"', ["%{$escaped}%"]));
        }

        $reports = $query->paginate(25)->withQueryString();

        $stats = [
            'drafts' => StrReport::where('status', StrReportStatus::Draft->value)->count(),
            'submitted' => StrReport::where('status', StrReportStatus::Submitted->value)->count(),
            'acknowledged' => StrReport::where('status', StrReportStatus::Acknowledged->value)->count(),
            'rejected' => StrReport::where('status', StrReportStatus::Rejected->value)->count(),
        ];

        return view('compliance.str.index', compact('reports', 'stats'));
    }

    public function show(StrReport $strReport): View
    {
        $this->authorize('view', $strReport);

        $strReport->load(['customer', 'createdBy', 'case']);

        return view('compliance.str.show', ['report' => $strReport]);
    }

    public function submit(SubmitStrReportRequest $request, StrReport $strReport): RedirectResponse
    {
        $this->authorize('update', $strReport);

        try {
            $this->strReportService->submit(
                $strReport,
                (string) $request->validated('bnm_reference'),
                $request->user()
            );
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'STR submit failed', 'Failed to submit STR.', ['str_report_id' => $strReport->id]);
        }

        return back()->with('success', 'STR submitted to BNM.');
    }

    public function acknowledge(Request $request, StrReport $strReport): RedirectResponse
    {
        $this->authorize('update', $strReport);

        try {
            $this->strReportService->acknowledge($strReport, $request->user());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'STR acknowledge failed', 'Failed to acknowledge STR.', ['str_report_id' => $strReport->id]);
        }

        return back()->with('success', 'STR acknowledged by BNM.');
    }

    /**
     * Export STR records as CSV using BNM-style column order.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', StrReport::class);

        $query = StrReport::with(['customer'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $filename = 'str-export-'.now()->format('Ymd-His').'.csv';

        // Regulatory exports are access-audited (severity resolved from the
        // report_* severity map: report_data_export -> WARNING).
        $this->auditService->logAction('report_data_export', 'StrReport', null, [
            'user_id' => auth()->id(),
            'new_values' => [
                'export' => 'csv',
                'filters' => $request->only(['status']),
            ],
        ]);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new FileOperationException('Unable to open output stream for CSV export.');
            }

            fputcsv($handle, [
                'Reference',
                'BNM Reference',
                'Customer Masked ID',
                'Trigger Amount (MYR)',
                'Trigger Reason',
                'Status',
                'Submitted At',
                'Acknowledged At',
                'Created At',
            ]);

            $query->chunkById(500, function ($reports) use ($handle): void {
                foreach ($reports as $report) {
                    fputcsv($handle, [
                        $report->reference(),
                        $report->bnm_reference,
                        $report->customer->id_number ?? ('CUST-'.$report->customer_id),
                        number_format((float) $report->trigger_amount_myr, 4, '.', ''),
                        $report->trigger_reason,
                        $report->status->value,
                        optional($report->submitted_at)->toDateTimeString(),
                        optional($report->acknowledged_at)->toDateTimeString(),
                        optional($report->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
