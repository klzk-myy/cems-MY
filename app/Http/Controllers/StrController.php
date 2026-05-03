<?php

namespace App\Http\Controllers;

use App\Enums\StrStatus;
use App\Http\Requests\StoreStrRequest;
use App\Models\FlaggedTransaction;
use App\Models\StrReport;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\StrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StrController extends Controller
{
    protected StrReportService $strService;

    protected AuditService $auditService;

    protected ComplianceService $complianceService;

    public function __construct(
        StrReportService $strService,
        AuditService $auditService,
        ComplianceService $complianceService
    ) {
        $this->strService = $strService;
        $this->auditService = $auditService;
        $this->complianceService = $complianceService;
    }

    public function index(Request $request): View
    {
        $query = StrReport::with(['customer', 'creator', 'reviewer', 'approver', 'alert']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $strReports = $query->orderBy('created_at', 'desc')->paginate(20);

        $stats = [
            'draft' => StrReport::where('status', StrStatus::Draft->value)->count(),
            'pending_review' => StrReport::where('status', StrStatus::PendingReview->value)->count(),
            'pending_approval' => StrReport::where('status', StrStatus::PendingApproval->value)->count(),
            'submitted' => StrReport::where('status', StrStatus::Submitted->value)->count(),
            'acknowledged' => StrReport::where('status', StrStatus::Acknowledged->value)->count(),
        ];

        return view('pages.str.index', compact('strReports', 'stats'));
    }

    public function create(Request $request): View
    {
        $alertId = $request->get('alert_id');
        $alert = null;
        $customer = null;

        if ($alertId) {
            $alert = FlaggedTransaction::with(['transaction.customer'])->find($alertId);
            $customer = $alert?->transaction?->customer;
        }

        $pendingAlerts = FlaggedTransaction::where('status', 'Open')
            ->orWhere('status', 'Under_Review')
            ->with(['transaction.customer'])
            ->get();

        return view('pages.str.create', compact('alert', 'customer', 'pendingAlerts'));
    }

    public function store(StoreStrRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $strReport = $this->strService->createStrReport($validated, Auth::user());

            return redirect()->route('str.show', $strReport)
                ->with('success', 'STR draft created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create STR: '.$e->getMessage())
                ->withInput();
        }
    }

    public function show(StrReport $str): View
    {
        $str->load(['customer', 'creator', 'reviewer', 'approver', 'alert']);
        $transactions = $str->transactions();

        return view('str.show', compact('str', 'transactions'));
    }

    public function generateFromAlert(FlaggedTransaction $flaggedTransaction): RedirectResponse
    {
        app('log')->info('StrController generateFromAlert', [
            'flaggedTransaction_id' => $flaggedTransaction->getKey(),
            'flaggedTransaction_exists' => $flaggedTransaction->exists,
            'alert_attributes' => $flaggedTransaction->getAttributes(),
        ]);

        try {
            $alert = FlaggedTransaction::with(['transaction.customer'])->findOrFail($flaggedTransaction->id);

            $strReport = $this->strService->generateFromAlert($alert);

            return redirect()->route('str.show', $strReport)
                ->with('success', 'STR draft generated from alert.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to generate STR: '.$e->getMessage());
        }
    }

    public function submitForReview(StrReport $str): RedirectResponse
    {
        if (! $str->isDraft()) {
            return redirect()->back()->with('error', 'Only draft STRs can be submitted for review.');
        }

        $oldStatus = $str->status->value;
        $str->update(['status' => 'pending_review']);

        $this->auditService->logStrAction('str_submitted_for_review', $str->id, [
            'old' => ['status' => $oldStatus],
            'new' => ['status' => 'pending_review'],
        ]);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR submitted for compliance manager review.');
    }

    public function submitForApproval(StrReport $str): RedirectResponse
    {
        if (! $str->isPendingReview()) {
            return redirect()->back()->with('error', 'Only STRs pending review can be submitted for approval.');
        }

        $oldStatus = $str->status->value;
        $str->update([
            'status' => 'pending_approval',
            'reviewed_by' => Auth::id(),
        ]);

        $this->auditService->logStrAction('str_submitted_for_approval', $str->id, [
            'old' => ['status' => $oldStatus],
            'new' => [
                'status' => 'pending_approval',
                'reviewed_by' => Auth::id(),
                'reviewed_by_name' => Auth::user()->username,
            ],
        ]);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR submitted for principal officer approval.');
    }

    public function approve(StrReport $str): RedirectResponse
    {
        if (! $str->isPendingApproval()) {
            return redirect()->back()->with('error', 'Only STRs pending approval can be approved.');
        }

        $oldStatus = $str->status->value;
        $str->update([
            'status' => StrStatus::Submitted->value,
            'approved_by' => Auth::id(),
        ]);

        $this->auditService->logStrAction('str_approved', $str->id, [
            'old' => ['status' => $oldStatus],
            'new' => [
                'status' => 'Submitted',
                'approved_by' => Auth::id(),
                'approved_by_name' => Auth::user()->username,
            ],
            'severity' => 'WARNING',
        ]);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR approved. Ready for goAML submission.');
    }

    public function submit(StrReport $str): RedirectResponse
    {
        if (! $str->status->canSubmit()) {
            return redirect()->back()->with('error', 'STR cannot be submitted in its current status.');
        }

        $oldStatus = $str->status->value;
        $submitted = $this->strService->submitToGoAML($str);

        if ($submitted) {
            $this->auditService->logStrAction('str_submitted_to_goaml', $str->id, [
                'old' => ['status' => $oldStatus],
                'new' => [
                    'status' => 'submitted',
                    'submitted_at' => now()->toDateTimeString(),
                    'submitted_by' => Auth::id(),
                ],
                'severity' => 'CRITICAL',
            ]);

            return redirect()->route('str.show', $str)
                ->with('success', 'STR submitted to goAML successfully.');
        }

        return redirect()->back()
            ->with('error', 'Failed to submit STR to goAML. Please try again.');
    }

    public function trackAcknowledgment(Request $request, StrReport $str): RedirectResponse
    {
        $request->validate([
            'bnm_reference' => 'required|string|max:100',
        ]);

        if (! $str->isSubmitted()) {
            return redirect()->back()->with('error', 'Only submitted STRs can be acknowledged.');
        }

        $this->strService->trackSubmission($str, $request->bnm_reference);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR acknowledgment tracked with BNM reference: '.$request->bnm_reference);
    }

    public function update(Request $request, StrReport $str): RedirectResponse
    {
        if (! $str->isDraft()) {
            return redirect()->back()->with('error', 'Only draft STRs can be edited.');
        }

        $request->validate([
            'reason' => 'required|string|min:20',
            'transaction_ids' => 'required|array',
            'supporting_documents' => 'nullable|array',
        ]);

        $str->update([
            'reason' => $request->reason,
            'transaction_ids' => $request->transaction_ids,
            'supporting_documents' => $request->supporting_documents ?? [],
        ]);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR updated successfully.');
    }

    public function edit(StrReport $str): View
    {
        if (! $str->isDraft()) {
            return redirect()->route('str.show', $str)
                ->with('error', 'Only draft STRs can be edited.');
        }

        $str->load(['customer', 'transactions']);

        return view('str.edit', compact('str'));
    }
}
