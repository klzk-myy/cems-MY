<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Exceptions\Domain\CaseManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCaseLinkRequest;
use App\Http\Requests\CreateCaseFromAlertsRequest;
use App\Http\Requests\LinkAlertToCaseRequest;
use App\Http\Requests\MergeCasesRequest;
use App\Http\Requests\UpdateCaseStatusRequest;
use App\Http\Requests\UploadCaseDocumentRequest;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseManagementController extends Controller
{
    public function __construct(
        protected CaseManagementService $caseManagementService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ComplianceCase::class);

        $query = ComplianceCase::with(['customer', 'assignee', 'alerts'])
            ->open();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $cases = $query->orderByRaw("CASE priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END")
            ->orderBy('sla_deadline')
            ->paginate(25);

        $summary = $this->caseManagementService->getCaseSummary();

        return view('compliance.cases.index', compact('cases', 'summary'));
    }

    public function store(CreateCaseFromAlertsRequest $request): RedirectResponse
    {
        $this->authorize('create', ComplianceCase::class);

        $validated = $request->validated();

        try {
            $case = $this->caseManagementService->createFromAlerts(
                $validated['alert_ids'],
                (int) auth()->id()
            );
        } catch (CaseManagementException $e) {
            return redirect()->back()->with('error', 'Failed to create case. Please try again.');
        }

        return redirect()->route('compliance.cases.show', $case->id)
            ->with('success', 'Case created successfully');
    }

    public function show(ComplianceCase $case): View
    {
        $this->authorize('view', $case);

        $case->load(['customer', 'assignee', 'alerts', 'alerts.flaggedTransaction']);

        return view('compliance.cases.show', compact('case'));
    }

    public function update(UpdateCaseStatusRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validated();

        try {
            if (isset($validated['status'])) {
                $this->caseManagementService->updateStatus(
                    $case,
                    ComplianceCaseStatus::from($validated['status'])
                );
            }
        } catch (CaseManagementException $e) {
            return redirect()->back()->with('error', 'Failed to update case. Please try again.');
        }

        return redirect()->back()->with('success', 'Case updated successfully');
    }

    public function merge(MergeCasesRequest $request, ComplianceCase $case): RedirectResponse
    {
        // Merging mutates both records, so both need update permission.
        $this->authorize('update', $case);

        /** @var ComplianceCase $targetCase */
        $targetCase = ComplianceCase::findOrFail($request->target_case_id);
        $this->authorize('update', $targetCase);

        try {
            $mergedCase = $this->caseManagementService->mergeCases($case, $targetCase);
        } catch (CaseManagementException $e) {
            return redirect()->back()->with('error', 'Failed to merge cases. Please try again.');
        }

        return redirect()->route('compliance.cases.show', $mergedCase->id)
            ->with('success', 'Cases merged successfully');
    }

    public function linkAlert(LinkAlertToCaseRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        /** @var Alert $alert */
        $alert = Alert::findOrFail($request->alert_id);

        try {
            $this->caseManagementService->linkAlertToCase($alert, $case);
        } catch (CaseManagementException $e) {
            return redirect()->back()->with('error', 'Failed to link alert to case. Please try again.');
        }

        return redirect()->back()->with('success', 'Alert linked to case');
    }

    public function uploadDocument(UploadCaseDocumentRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $document = $this->caseManagementService->addDocument(
            $case->id,
            $request->file('file'),
            (int) auth()->id()
        );

        return redirect()->back()->with('success', 'Document uploaded');
    }

    public function verifyDocument(Request $request, ComplianceCase $case, ComplianceCaseDocument $document): RedirectResponse
    {
        $this->authorize('update', $case);

        if ($document->case_id !== $case->id) {
            abort(403, 'Document does not belong to this case');
        }

        $this->caseManagementService->verifyDocument($document->id, (int) auth()->id());

        return redirect()->back()->with('success', 'Document verified');
    }

    public function addLink(AddCaseLinkRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $this->caseManagementService->addLink($case->id, $request->linked_type, $request->linked_id);

        return redirect()->back()->with('success', 'Link added');
    }

    public function removeLink(ComplianceCase $case, ComplianceCaseLink $link): RedirectResponse
    {
        $this->authorize('update', $case);

        if ($link->case_id !== $case->id) {
            abort(403, 'Link does not belong to this case');
        }

        $this->caseManagementService->removeLink($link->id);

        return redirect()->back()->with('success', 'Link removed');
    }

    public function escalate(ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $this->caseManagementService->escalateCase($case);

        return redirect()->back()->with('success', 'Case escalated successfully');
    }
}
