<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\DismissFindingRequest;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FindingController extends Controller
{
    public function index(Request $request)
    {
        $query = ComplianceFinding::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->has('type')) {
            $query->where('finding_type', $request->input('type'));
        }
        if ($request->has('from_date')) {
            $query->whereDate('generated_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('generated_at', '<=', $request->input('to_date'));
        }

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

        $total = ComplianceFinding::count();
        $newCount = ComplianceFinding::new()->count();

        $bySeverity = ComplianceFinding::query()
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $byStatus = ComplianceFinding::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byType = ComplianceFinding::query()
            ->selectRaw('finding_type, count(*) as count')
            ->groupBy('finding_type')
            ->pluck('count', 'finding_type');

        $stats = [
            'total' => $total,
            'new' => $newCount,
            'by_severity' => $bySeverity,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];

        $pagination = [
            'current_page' => $findingsPaginated->currentPage(),
            'last_page' => $findingsPaginated->lastPage(),
            'per_page' => $findingsPaginated->perPage(),
            'total' => $findingsPaginated->total(),
        ];

        return view('compliance.findings.index', compact('findings', 'stats', 'pagination'));
    }

    public function show(int $id)
    {
        $finding = ComplianceFinding::with('subject')->find($id);

        if (! $finding) {
            return redirect()->route('compliance.findings.index')
                ->with('error', 'Finding not found');
        }

        $findingData = [
            'id' => $finding->id,
            'finding_type' => $finding->finding_type?->value,
            'severity' => $finding->severity?->value,
            'status' => $finding->status?->value,
            'details' => $finding->details,
            'generated_at' => $finding->generated_at?->toIso8601String(),
            'subject_type' => $finding->subject_type,
            'subject_id' => $finding->subject_id,
        ];

        return view('compliance.findings.show', compact('finding'));
    }

    public function dismiss(DismissFindingRequest $request, int $id)
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
