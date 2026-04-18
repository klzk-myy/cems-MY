<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    /**
     * List compliance findings with filtering.
     */
    public function index(Request $request): JsonResponse
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
        if ($request->has('date_from')) {
            $query->whereDate('generated_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('generated_at', '<=', $request->input('date_to'));
        }

        $perPage = $request->get('per_page', 20);
        $findings = $query->orderBy('generated_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $findings,
        ]);
    }

    /**
     * Get a specific finding.
     */
    public function show(int $id): JsonResponse
    {
        $finding = ComplianceFinding::with('subject')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $finding,
        ]);
    }

    /**
     * Dismiss a finding.
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $finding = ComplianceFinding::findOrFail($id);
        $finding->dismiss($validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Finding dismissed.',
            'data' => $finding,
        ]);
    }

    /**
     * Get finding statistics.
     */
    public function stats(): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'new' => $newCount,
                'by_severity' => $bySeverity,
                'by_status' => $byStatus,
                'by_type' => $byType,
            ],
        ]);
    }
}
