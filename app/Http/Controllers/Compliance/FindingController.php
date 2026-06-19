<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Concerns\FiltersComplianceFindings;
use App\Http\Controllers\Controller;
use App\Http\Requests\DismissFindingRequest;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FindingController extends Controller
{
    use FiltersComplianceFindings;

    public function index(Request $request): View
    {
        $query = ComplianceFinding::query();

        $this->applyFindingFilters($query, $request);

        $perPage = $request->get('per_page', 20);
        $findingsPaginated = $query->orderBy('generated_at', 'desc')->paginate($perPage);

        $findings = $findingsPaginated->map(fn ($finding) => [
            'id' => $finding->id,
            'finding_type' => $finding->finding_type?->value,
            'severity' => $finding->severity?->value,
            'status' => $finding->status?->value,
            'details' => $finding->details,
            'generated_at' => $finding->generated_at?->toIso8601String(),
        ]);

        $stats = $this->getFindingStats();

        $pagination = [
            'current_page' => $findingsPaginated->currentPage(),
            'last_page' => $findingsPaginated->lastPage(),
            'per_page' => $findingsPaginated->perPage(),
            'total' => $findingsPaginated->total(),
        ];

        return view('compliance.findings.index', compact('findings', 'stats', 'pagination'));
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

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('FindingController: Exception dismissing finding', [
                'message' => $e->getMessage(),
                'finding_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Failed to dismiss finding');
        }
    }
}
