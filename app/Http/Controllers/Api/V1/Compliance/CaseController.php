<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function __construct(
        protected CaseManagementService $caseService
    ) {}

    /**
     * List cases with filtering.
     */
    public function index(Request $request): JsonResponse
    {
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

        $perPage = $request->get('per_page', 20);
        $cases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cases,
        ]);
    }

    /**
     * Get a specific case.
     */
    public function show(int $id): JsonResponse
    {
        $case = ComplianceCase::with(['customer', 'assignee', 'notes.author', 'documents'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $case,
        ]);
    }

    /**
     * Create a case.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'finding_id' => 'nullable|exists:compliance_findings,id',
            'case_type' => 'required|string',
            'assigned_to' => 'required|exists:users,id',
            'summary' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|exists:customers,id',
            'severity' => 'nullable|string',
        ]);

        if (! empty($validated['finding_id'])) {
            $finding = ComplianceFinding::findOrFail($validated['finding_id']);
            $case = $this->caseService->createCaseFromFinding(
                finding: $finding,
                caseType: ComplianceCaseType::from($validated['case_type']),
                assignedTo: $validated['assigned_to'],
                summary: $validated['summary'] ?? null
            );
        } else {
            $case = $this->caseService->createManualCase(
                caseType: ComplianceCaseType::from($validated['case_type']),
                customerId: $validated['customer_id'] ?? 0,
                assignedTo: $validated['assigned_to'],
                severity: FindingSeverity::from($validated['severity'] ?? 'Medium'),
                summary: $validated['summary'] ?? null
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Case created successfully.',
            'data' => $case,
        ], 201);
    }

    /**
     * Update a case.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string',
            'case_summary' => 'nullable|string|max:1000',
        ]);

        $case = ComplianceCase::findOrFail($id);

        if (! empty($validated['assigned_to'])) {
            $this->caseService->assignCase($case, $validated['assigned_to']);
        }

        if (! empty($validated['case_summary'])) {
            $case->update(['case_summary' => $validated['case_summary']]);
        }

        if (! empty($validated['priority'])) {
            $case->update(['priority' => $validated['priority']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Case updated successfully.',
            'data' => $case->fresh(),
        ]);
    }

    /**
     * Add a note to a case.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'note_type' => 'required|string',
            'content' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ]);

        $case = ComplianceCase::findOrFail($id);

        $note = $this->caseService->addNote(
            case: $case,
            authorId: auth()->id(),
            noteType: CaseNoteType::from($validated['note_type']),
            content: $validated['content'],
            isInternal: $validated['is_internal'] ?? true
        );

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully.',
            'data' => $note,
        ], 201);
    }

    /**
     * Close a case.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $case = ComplianceCase::findOrFail($id);

        $case = $this->caseService->closeCase(
            case: $case,
            resolution: CaseResolution::from($validated['resolution']),
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Case closed successfully.',
            'data' => $case,
        ]);
    }

    /**
     * Escalate a case.
     */
    public function escalate(int $id): JsonResponse
    {
        $case = ComplianceCase::findOrFail($id);
        $case = $this->caseService->escalateCase($case);

        return response()->json([
            'success' => true,
            'message' => 'Case escalated successfully.',
            'data' => $case,
        ]);
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
                'author' => $note->author?->full_name ?? 'Unknown',
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

        return response()->json([
            'success' => true,
            'data' => $timeline->sortBy('timestamp')->values(),
        ]);
    }
}
