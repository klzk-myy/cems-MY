<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCaseStatus;
use App\Exceptions\Domain\CaseManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCaseNoteRequest;
use App\Http\Requests\CreateCaseFromAlertsRequest;
use App\Http\Requests\UpdateCaseStatusRequest;
use App\Models\Compliance\ComplianceCase;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseManagementController extends Controller
{
    public function __construct(
        protected CaseManagementService $caseManagementService,
        protected AlertTriageService $alertTriageService
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

        $case->load(['customer', 'assignee', 'alerts', 'alerts.flaggedTransaction', 'notes.author', 'documents']);

        $officers = $this->alertTriageService->getAvailableOfficers()->pluck('username', 'id');
        $statusOptions = collect(ComplianceCaseStatus::cases())
            ->filter(fn (ComplianceCaseStatus $status) => $case->status->canMoveTo($status))
            ->mapWithKeys(fn (ComplianceCaseStatus $status) => [$status->value => $status->label()]);
        $resolutionOptions = collect(CaseResolution::cases())
            ->mapWithKeys(fn (CaseResolution $resolution) => [$resolution->value => $resolution->label()]);
        $noteTypes = collect(CaseNoteType::cases())
            ->mapWithKeys(fn (CaseNoteType $type) => [$type->value => $type->label()]);

        return view('compliance.cases.show', compact('case', 'officers', 'statusOptions', 'resolutionOptions', 'noteTypes'));
    }

    public function update(UpdateCaseStatusRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validated();

        try {
            if (isset($validated['assigned_to'])) {
                $this->caseManagementService->assignToOfficer($case, (int) $validated['assigned_to']);
            }

            if (isset($validated['status'])) {
                $status = ComplianceCaseStatus::from($validated['status']);

                if ($status === ComplianceCaseStatus::Closed) {
                    $this->caseManagementService->closeCase(
                        $case,
                        CaseResolution::from((string) $validated['resolution']),
                        $validated['notes'] ?? null
                    );
                } else {
                    $this->caseManagementService->updateStatus($case, $status);
                }
            }
        } catch (CaseManagementException $e) {
            return redirect()->back()->with('error', 'Failed to update case. Please try again.');
        }

        return redirect()->back()->with('success', 'Case updated successfully');
    }

    public function addNote(AddCaseNoteRequest $request, ComplianceCase $case): RedirectResponse
    {
        $this->authorize('addNote', $case);

        $validated = $request->validated();

        $this->caseManagementService->addNote(
            $case,
            (int) auth()->id(),
            CaseNoteType::from($validated['note_type']),
            $validated['content'],
            $request->boolean('is_internal')
        );

        return redirect()->back()->with('success', 'Note added');
    }

    public function escalate(ComplianceCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $this->caseManagementService->escalateCase($case);

        return redirect()->back()->with('success', 'Case escalated successfully');
    }
}
