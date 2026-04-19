<?php

namespace App\Http\Controllers;

use App\Enums\StrStatus;
use App\Models\FlaggedTransaction;
use App\Models\StrReport;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\StrReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    public function index(Request $request)
    {
        $query = StrReport::with(['customer', 'creator', 'reviewer', 'approver', 'alert']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $strReports = $query->orderBy('created_at', 'desc')->paginate(20);

        $stats = [
            'draft' => StrReport::where('status', 'draft')->count(),
            'pending_review' => StrReport::where('status', 'pending_review')->count(),
            'pending_approval' => StrReport::where('status', 'pending_approval')->count(),
            'submitted' => StrReport::where('status', 'submitted')->count(),
            'acknowledged' => StrReport::where('status', 'acknowledged')->count(),
        ];

        return view('str.index', compact('strReports', 'stats'));
    }

    public function create(Request $request)
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

        return view('str.create', compact('alert', 'customer', 'pendingAlerts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'alert_id' => 'nullable|exists:flagged_transactions,id',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'reason' => 'required|string|min:20',
        ]);

        try {
            $strReport = StrReport::create([
                'str_no' => $this->strService->generateStrNumber(),
                'branch_id' => Auth::user()->branch_id,
                'customer_id' => $request->customer_id,
                'alert_id' => $request->alert_id,
                'transaction_ids' => $request->transaction_ids,
                'reason' => $request->reason,
                'supporting_documents' => [],
                'status' => 'draft',
                'created_by' => Auth::id(),
                'suspicion_date' => now(), // Default to now, can be backdated
            ]);

            // Calculate filing deadline
            $deadlineInfo = $this->complianceService->calculateStrDeadline(now());
            $strReport->filing_deadline = $deadlineInfo['deadline'];
            $strReport->save();

            // Audit log
            $this->auditService->logStrAction('str_created', $strReport->id, [
                'new' => [
                    'str_no' => $strReport->str_no,
                    'customer_id' => $strReport->customer_id,
                    'suspicion_date' => $strReport->suspicion_date->toDateTimeString(),
                    'filing_deadline' => $deadlineInfo['deadline']->toDateTimeString(),
                ],
            ]);

            return redirect()->route('str.show', $strReport)
                ->with('success', 'STR draft created successfully. Filing deadline: '.$deadlineInfo['deadline']->format('Y-m-d H:i'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create STR: '.$e->getMessage())
                ->withInput();
        }
    }

    public function show(StrReport $str)
    {
        $str->load(['customer', 'creator', 'reviewer', 'approver', 'alert']);
        $transactions = $str->transactions();

        return view('str.show', compact('str', 'transactions'));
    }

    public function generateFromAlert(FlaggedTransaction $flaggedTransaction)
    {
        Log::info('StrController generateFromAlert', [
            'flaggedTransaction_id' => $flaggedTransaction->getKey(),
            'flaggedTransaction_exists' => $flaggedTransaction->exists,
            'alert_attributes' => $flaggedTransaction->getAttributes(),
        ]);

        try {
            // Explicitly find the flag to ensure it's loaded from DB
            $alert = FlaggedTransaction::with(['transaction.customer'])->findOrFail($flaggedTransaction->id);

            $strReport = $this->strService->generateFromAlert($alert);

            return redirect()->route('str.show', $strReport)
                ->with('success', 'STR draft generated from alert.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to generate STR: '.$e->getMessage());
        }
    }

    public function submitForReview(StrReport $str)
    {
        if (! $str->isDraft()) {
            return redirect()->back()->with('error', 'Only draft STRs can be submitted for review.');
        }

        $oldStatus = $str->status->value;
        $str->update(['status' => 'pending_review']);

        // Audit log
        $this->auditService->logStrAction('str_submitted_for_review', $str->id, [
            'old' => ['status' => $oldStatus],
            'new' => ['status' => 'pending_review'],
        ]);

        return redirect()->route('str.show', $str)
            ->with('success', 'STR submitted for compliance manager review.');
    }

    public function submitForApproval(StrReport $str)
    {
        if (! $str->isPendingReview()) {
            return redirect()->back()->with('error', 'Only STRs pending review can be submitted for approval.');
        }

        $oldStatus = $str->status->value;
        $str->update([
            'status' => 'pending_approval',
            'reviewed_by' => Auth::id(),
        ]);

        // Audit log
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

    public function approve(StrReport $str)
    {
        if (! $str->isPendingApproval()) {
            return redirect()->back()->with('error', 'Only STRs pending approval can be approved.');
        }

        $oldStatus = $str->status->value;
        $str->update([
            'status' => StrStatus::Submitted->value,
            'approved_by' => Auth::id(),
        ]);

        // Audit log
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

    public function submit(StrReport $str)
    {
        if (! $str->status->canSubmit()) {
            return redirect()->back()->with('error', 'STR cannot be submitted in its current status.');
        }

        $oldStatus = $str->status->value;
        $submitted = $this->strService->submitToGoAML($str);

        if ($submitted) {
            // Audit log
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

    public function trackAcknowledgment(Request $request, StrReport $str)
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

    public function update(Request $request, StrReport $str)
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

    public function edit(StrReport $str)
    {
        if (! $str->isDraft()) {
            return redirect()->route('str.show', $str)
                ->with('error', 'Only draft STRs can be edited.');
        }

        $str->load(['customer', 'transactions']);

        return view('str.edit', compact('str'));
    }
}
