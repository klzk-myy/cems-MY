<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\AddCaseNoteRequest;
use App\Http\Requests\Api\V1\Compliance\CaseIndexRequest;
use App\Http\Requests\Api\V1\Compliance\CloseCaseRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Resources\Api\V1\CaseNoteResource;
use App\Http\Resources\Api\V1\Compliance\CaseCollection;
use App\Http\Resources\Api\V1\Compliance\CaseResource;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\JsonResponse;

class CaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CaseManagementService $caseService
    ) {}

    /**
     * List cases with filtering.
     */
    public function index(CaseIndexRequest $request): CaseCollection
    {
        $this->authorize('viewAny', ComplianceCase::class);

        $query = ComplianceCase::with(['customer', 'assignee']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('case_type', $request->input('type'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $cases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->resourceWithSuccess(new CaseCollection($cases), 'Cases retrieved successfully.');
    }

    /**
     * Get a specific case.
     */
    public function show(int $id): JsonResponse
    {
        $case = ComplianceCase::with(['customer', 'assignee', 'notes.author', 'documents'])
            ->findOrFail($id);

        $this->authorize('view', $case);

        return $this->successResponse(new CaseResource($case), 'Case retrieved successfully.');
    }

    /**
     * Create a case.
     */
    public function store(StoreCaseRequest $request): JsonResponse
    {
        $this->authorize('create', ComplianceCase::class);

        $validated = $request->validated();

        if (! empty($validated['finding_id'])) {
            /** @var ComplianceFinding $finding */
            $finding = ComplianceFinding::findOrFail($validated['finding_id']);
            $case = $this->caseService->createCaseFromFinding(
                finding: $finding,
                caseType: ComplianceCaseType::from($validated['case_type']),
                assignedTo: $validated['assigned_to'],
                summary: $validated['summary'] ?? null
            );
        } else {
            // Without a finding, a customer must be provided. The request
            // validation enforces required_without, but guard here too so a 0
            // (which violates the customers FK) can never reach the service.
            $case = $this->caseService->createManualCase(
                caseType: ComplianceCaseType::from($validated['case_type']),
                customerId: $validated['customer_id'],
                assignedTo: $validated['assigned_to'],
                severity: FindingSeverity::from($validated['severity'] ?? FindingSeverity::Medium->value),
                summary: $validated['summary'] ?? null
            );
        }

        return $this->successResponse(new CaseResource($case), 'Case created successfully.', 201);
    }

    /**
     * Update a case.
     */
    public function update(UpdateCaseRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $case = ComplianceCase::findOrFail($id);

        $this->authorize('update', $case);

        if (! empty($validated['assigned_to'])) {
            $this->caseService->assignToOfficer($case, $validated['assigned_to']);
        }

        if (! empty($validated['case_summary'])) {
            $case->update(['case_summary' => $validated['case_summary']]);
        }

        if (! empty($validated['priority'])) {
            // Convert to the enum before persisting: the priority cast is
            // ComplianceCasePriority, and writing the raw string would raise a
            // ValueError inside the cast on the next read.
            $case->update(['priority' => ComplianceCasePriority::from($validated['priority'])]);
        }

        return $this->successResponse(new CaseResource($case->fresh()), 'Case updated successfully.');
    }

    /**
     * Add a note to a case.
     */
    public function addNote(AddCaseNoteRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $case = ComplianceCase::findOrFail($id);

        $this->authorize('addNote', $case);

        $note = $this->caseService->addNote(
            case: $case,
            authorId: (int) auth()->id(),
            noteType: CaseNoteType::from($validated['note_type']),
            content: $validated['content'],
            isInternal: $validated['is_internal'] ?? true
        );

        return $this->successResponse(new CaseNoteResource($note), 'Note added successfully.', 201);
    }

    /**
     * Close a case.
     */
    public function close(CloseCaseRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $case = ComplianceCase::findOrFail($id);

        $this->authorize('update', $case);

        $case = $this->caseService->closeCase(
            case: $case,
            resolution: CaseResolution::from($validated['resolution']),
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse(new CaseResource($case), 'Case closed successfully.');
    }

    /**
     * Escalate a case.
     */
    public function escalate(int $id): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);
        $this->authorize('update', $case);
        $case = $this->caseService->escalateCase($case);

        return $this->successResponse(new CaseResource($case), 'Case escalated successfully.');
    }

    /**
     * Get case event timeline.
     */
    public function timeline(int $id): JsonResponse
    {
        $case = ComplianceCase::with(['notes.author', 'documents'])->findOrFail($id);

        $timeline = collect();

        $timeline->push([
            'type' => 'created',
            'timestamp' => $case->created_at->toIso8601String(),
            'description' => 'Case created',
        ]);

        foreach ($case->notes as $note) {
            $timeline->push([
                'type' => 'note',
                'timestamp' => $note->created_at->toIso8601String(),
                'author' => $note->author->full_name ?? 'Unknown',
                'content' => $note->content,
                'note_type' => $note->note_type->value,
            ]);
        }

        if ($case->escalated_at) {
            $timeline->push([
                'type' => 'escalation',
                'timestamp' => $case->escalated_at->toIso8601String(),
                'description' => 'Case escalated',
            ]);
        }

        if ($case->resolved_at) {
            $timeline->push([
                'type' => 'closed',
                'timestamp' => $case->resolved_at->toIso8601String(),
                'description' => 'Case closed with resolution: '.$case->resolution?->value,
            ]);
        }

        return $this->successResponse($timeline->sortBy('timestamp')->values(), 'Case timeline retrieved successfully.');
    }
}
