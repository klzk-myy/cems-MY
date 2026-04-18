<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\Request;

class CaseManagementController extends Controller
{
    public function __construct(
        protected CaseManagementService $caseManagementService
    ) {}

    public function index(Request $request)
    {
        $query = ComplianceCase::with(['customer', 'assignedTo', 'alerts'])
            ->open();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $cases = $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('sla_deadline')
            ->paginate(25);

        $summary = $this->caseManagementService->getCaseSummary();

        return view('compliance.cases.index', compact('cases', 'summary'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'exists:alerts,id',
        ]);

        $case = $this->caseManagementService->createFromAlerts(
            $request->alert_ids,
            auth()->id()
        );

        return redirect()->route('compliance.cases.show', $case->id)
            ->with('success', 'Case created successfully');
    }

    public function show(ComplianceCase $case)
    {
        $case->load(['customer', 'assignedTo', 'openedBy', 'alerts', 'alerts.flaggedTransaction']);

        return view('compliance.cases.show', compact('case'));
    }

    public function update(Request $request, ComplianceCase $case)
    {
        $request->validate([
            'status' => 'nullable|in:open,in_progress,pending_review,resolved,closed',
            'notes' => 'nullable|string',
        ]);

        if ($request->has('status')) {
            $case = $this->caseManagementService->updateStatus($case, $request->status);
        }

        if ($request->has('notes')) {
            $case->update(['notes' => $request->notes]);
        }

        return redirect()->back()->with('success', 'Case updated successfully');
    }

    public function merge(Request $request, ComplianceCase $case)
    {
        $request->validate([
            'target_case_id' => 'required|exists:compliance_cases,id',
        ]);

        $targetCase = ComplianceCase::findOrFail($request->target_case_id);

        $mergedCase = $this->caseManagementService->mergeCases($case, $targetCase);

        return redirect()->route('compliance.cases.show', $mergedCase->id)
            ->with('success', 'Cases merged successfully');
    }

    public function linkAlert(Request $request, ComplianceCase $case)
    {
        $request->validate([
            'alert_id' => 'required|exists:alerts,id',
        ]);

        $alert = Alert::findOrFail($request->alert_id);

        $this->caseManagementService->linkAlertToCase($alert, $case);

        return redirect()->back()->with('success', 'Alert linked to case');
    }

    public function uploadDocument(Request $request, ComplianceCase $case)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $document = $this->caseManagementService->addDocument(
            $case->id,
            $request->file('file'),
            auth()->id()
        );

        return redirect()->back()->with('success', 'Document uploaded');
    }

    public function verifyDocument(Request $request, ComplianceCase $case, ComplianceCaseDocument $document)
    {
        if ($document->case_id !== $case->id) {
            abort(403, 'Document does not belong to this case');
        }

        $this->caseManagementService->verifyDocument($document->id, auth()->id());

        return redirect()->back()->with('success', 'Document verified');
    }

    public function addLink(Request $request, ComplianceCase $case)
    {
        $request->validate([
            'linked_type' => 'required|string',
            'linked_id' => 'required|integer',
        ]);

        $this->caseManagementService->addLink($case->id, $request->linked_type, $request->linked_id);

        return redirect()->back()->with('success', 'Link added');
    }

    public function removeLink(ComplianceCase $case, ComplianceCaseLink $link)
    {
        if ($link->case_id !== $case->id) {
            abort(403, 'Link does not belong to this case');
        }

        $this->caseManagementService->removeLink($link->id);

        return redirect()->back()->with('success', 'Link removed');
    }

    public function escalate(Request $request, ComplianceCase $case)
    {
        $this->caseManagementService->escalateCase($case);

        return redirect()->back()->with('success', 'Case escalated successfully');
    }
}
