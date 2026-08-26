<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\ComplianceCaseType;
use App\Http\Concerns\FiltersComplianceFindings;
use App\Http\Controllers\Controller;
use App\Http\Requests\DismissFindingRequest;
use App\Models\Compliance\ComplianceFinding;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FindingController extends Controller
{
    use FiltersComplianceFindings;

    public function __construct(
        protected CaseManagementService $caseService,
    ) {}

    public function index(Request $request): View
    {
        $query = ComplianceFinding::query()->with('subject');

        $this->applyFindingFilters($query, $request);

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $findingsPaginated = $query->orderBy('generated_at', 'desc')->paginate($perPage);

        $findings = $findingsPaginated->map(fn (ComplianceFinding $finding) => [
            'id' => $finding->id,
            'finding_type' => $finding->finding_type?->value,
            'severity' => $finding->severity?->value,
            'status' => $finding->status?->value,
            'subject_label' => $this->getSubjectLabel($finding),
            'details' => $finding->details,
            'generated_at' => $finding->generated_at?->toIso8601String(),
            'can_dismiss' => $finding->status?->canBeDismissed() ?? false,
            'can_create_case' => $finding->status->canCreateCase(),
        ]);

        $stats = $this->getFindingStats();

        return view('compliance.findings.index', [
            'findings' => $findings,
            'stats' => $stats,
            'paginator' => $findingsPaginated->withQueryString(),
        ]);
    }

    public function show(int $id): View|RedirectResponse
    {
        $finding = ComplianceFinding::with('subject')->find($id);

        if (! $finding) {
            return redirect()->route('compliance.findings.index')
                ->with('error', 'Finding not found');
        }

        return view('compliance.findings.show', compact('finding'));
    }

    public function createCase(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'case_type' => ['required', Rule::enum(ComplianceCaseType::class)],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $finding = ComplianceFinding::find($id);

        if (! $finding) {
            return redirect()->route('compliance.findings.index')
                ->with('error', 'Finding not found');
        }

        if (! $finding->status?->canCreateCase()) {
            return redirect()->route('compliance.findings.index')
                ->with('error', 'A case cannot be created from this finding');
        }

        try {
            $case = $this->caseService->createCaseFromFinding(
                finding: $finding,
                caseType: ComplianceCaseType::from($validated['case_type']),
                assignedTo: (int) auth()->id(),
                summary: $validated['summary'] ?? null,
            );

            return redirect()->route('compliance.cases.show', $case)
                ->with('success', 'Case created from finding');
        } catch (\Exception $e) {
            Log::error('FindingController: Exception creating case from finding', [
                'message' => $e->getMessage(),
                'finding_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Failed to create case');
        }
    }

    protected function getSubjectLabel(ComplianceFinding $finding): ?string
    {
        $subject = $finding->subject;

        if (! $subject) {
            return null;
        }

        $label = $subject->full_name
            ?? $subject->name
            ?? $subject->reference
            ?? $subject->reference_number
            ?? ('#'.$subject->getKey());

        return class_basename($subject).' · '.$label;
    }

    public function dismiss(DismissFindingRequest $request, int $id): RedirectResponse
    {
        $finding = ComplianceFinding::find($id);

        if (! $finding) {
            return redirect()->back()->with('error', 'Finding not found');
        }

        try {
            $finding->dismiss($request->validated('reason'));

            return redirect()->back()->with('success', 'Finding dismissed');
        } catch (\InvalidArgumentException $e) {
            Log::warning('FindingController: Failed to dismiss finding', [
                'finding_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to dismiss finding. Please try again.');
        } catch (\Exception $e) {
            Log::error('FindingController: Exception dismissing finding', [
                'message' => $e->getMessage(),
                'finding_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Failed to dismiss finding');
        }
    }
}
