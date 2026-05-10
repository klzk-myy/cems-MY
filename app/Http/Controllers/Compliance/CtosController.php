<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\CtosReport;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CtosController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $params = array_filter([
            'status' => $request->get('status'),
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
        ]);

        $query = CtosReport::with(['customer', 'creator', 'branch']);

        if ($user && ! $user->isAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->has('branch_id') && $request->get('branch_id') !== null) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->from_date) {
            $query->where('report_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->where('report_date', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 20);
        $reportsPaginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $reports = $reportsPaginated->map(fn ($report) => [
            'id' => $report->id,
            'ctos_number' => $report->ctos_number,
            'status' => $report->status?->value,
            'report_date' => $report->report_date?->format('Y-m-d'),
            'customer_name' => $report->customer_name,
            'amount_local' => $report->amount_local,
            'currency_code' => $report->currency_code,
            'created_at' => $report->created_at?->toIso8601String(),
        ]);

        $pagination = [
            'current_page' => $reportsPaginated->currentPage(),
            'last_page' => $reportsPaginated->lastPage(),
            'per_page' => $reportsPaginated->perPage(),
            'total' => $reportsPaginated->total(),
        ];

        $summary = [
            'total' => $reportsPaginated->total(),
            'draft' => CtosReport::where('status', 'draft')->count(),
            'submitted' => CtosReport::where('status', 'submitted')->count(),
            'acknowledged' => CtosReport::where('status', 'acknowledged')->count(),
            'rejected' => CtosReport::where('status', 'rejected')->count(),
        ];

        return view('compliance.ctos.index', compact('reports', 'pagination', 'summary'));
    }

    public function show(int $id)
    {
        $report = CtosReport::with(['customer', 'creator', 'branch', 'transaction'])->find($id);

        if (! $report) {
            return redirect()->route('compliance.ctos.index')
                ->with('error', 'CTOS report not found');
        }

        $reportData = [
            'id' => $report->id,
            'ctos_number' => $report->ctos_number,
            'status' => $report->status?->value,
            'report_date' => $report->report_date?->format('Y-m-d'),
            'customer_name' => $report->customer_name,
            'id_type' => $report->id_type,
            'id_number_masked' => $report->id_number_masked,
            'date_of_birth' => $report->date_of_birth?->format('Y-m-d'),
            'nationality' => $report->nationality,
            'amount_local' => $report->amount_local,
            'amount_foreign' => $report->amount_foreign,
            'currency_code' => $report->currency_code,
            'transaction_type' => $report->transaction_type,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'bnm_reference' => $report->bnm_reference,
        ];

        return view('compliance.ctos.show', compact('report'));
    }

    public function submit(int $id)
    {
        $ctos = CtosReport::find($id);

        if (! $ctos) {
            return redirect()->route('compliance.ctos.index')
                ->with('error', 'CTOS report not found');
        }

        if (! $ctos->isDraft()) {
            return redirect()->back()->with('error', 'CTOS report can only be submitted from Draft status.');
        }

        try {
            $oldStatus = $ctos->status->value;
            $submittedBy = Auth::id();

            $submissionRef = $this->generateBnmReference($ctos);
            $ctos->markAsSubmitted($submittedBy, $submissionRef);

            $this->auditService->logRegulatoryReportEvent('ctos_submitted', $ctos->id, [
                'old' => ['status' => $oldStatus],
                'new' => [
                    'status' => 'Submitted',
                    'submitted_at' => $ctos->submitted_at->toDateTimeString(),
                    'submitted_by' => $submittedBy,
                    'bnm_reference' => $submissionRef,
                ],
            ]);

            $this->auditService->logWithSeverity(
                'ctos_report_submitted_to_bnm',
                [
                    'user_id' => $submittedBy,
                    'entity_type' => 'CtosReport',
                    'entity_id' => $ctos->id,
                    'new_values' => [
                        'ctos_number' => $ctos->ctos_number,
                        'bnm_reference' => $submissionRef,
                        'submitted_at' => $ctos->submitted_at->toDateTimeString(),
                    ],
                ],
                'WARNING'
            );

            return redirect()->back()->with('success', 'CTOS report submitted to BNM successfully');
        } catch (\Exception $e) {
            Log::error('CtosController: Exception submitting CTOS report', [
                'message' => $e->getMessage(),
                'ctos_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Failed to submit CTOS report');
        }
    }

    private function generateBnmReference(CtosReport $ctos): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "CTOS-SUB-{$year}{$month}-";

        $lastSubmission = CtosReport::where('bnm_reference', 'like', $prefix.'%')
            ->orderBy('bnm_reference', 'desc')
            ->first();

        if ($lastSubmission) {
            $lastNumber = (int) substr($lastSubmission->bnm_reference, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}
