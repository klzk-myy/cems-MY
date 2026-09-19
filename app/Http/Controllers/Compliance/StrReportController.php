<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\StrReportStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitStrReportRequest;
use App\Models\Compliance\ComplianceCase;
use App\Models\StrReport;
use App\Services\AuditService;
use App\Services\Compliance\StrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            'drafts' => StrReport::where('status', StrReportStatus::Draft)->count(),
            'submitted' => StrReport::where('status', StrReportStatus::Submitted)->count(),
            'acknowledged' => StrReport::where('status', StrReportStatus::Acknowledged)->count(),
            'rejected' => StrReport::where('status', StrReportStatus::Rejected)->count(),
        ];

        return view('compliance.str.index', compact('reports', 'stats'));
    }

    public function show(StrReport $strReport): View
    {
        $this->authorize('view', $strReport);

        $strReport->load(['customer', 'createdBy', 'case']);

        return view('compliance.str.show', ['report' => $strReport]);
    }

    public function createFromCase(Request $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('create', StrReport::class);

        try {
            $report = $this->strReportService->createFromCase($case, $request->user());
        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('STR draft creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'case_id' => $case->id,
                'exception_class' => $e::class,
                'controller' => static::class,
                'action' => 'createFromCase',
            ]);

            return redirect()->route('compliance.cases.show', $case->id)
                ->with('error', 'Failed to create STR draft: '.$e->getMessage());
        }

        return redirect()->route('compliance.str.show', $report)
            ->with('success', 'STR draft created from case '.$case->case_number.'.');
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
        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('STR submit failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'str_report_id' => $strReport->id,
                'exception_class' => $e::class,
                'controller' => static::class,
                'action' => 'submit',
            ]);

            return back()->with('error', 'Failed to submit STR: '.$e->getMessage());
        }

        return back()->with('success', 'STR submitted to BNM.');
    }

    public function acknowledge(Request $request, StrReport $strReport): RedirectResponse
    {
        $this->authorize('update', $strReport);

        try {
            $this->strReportService->acknowledge($strReport, $request->user());
        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('STR acknowledge failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'str_report_id' => $strReport->id,
                'exception_class' => $e::class,
                'controller' => static::class,
                'action' => 'acknowledge',
            ]);

            return back()->with('error', 'Failed to acknowledge STR: '.$e->getMessage());
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
                throw new \RuntimeException('Unable to open output stream for CSV export.');
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
                        $report->customer->id_number_masked ?? ('CUST-'.$report->customer_id),
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
