<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Concerns\FiltersComplianceFindings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\DismissFindingRequest;
use App\Http\Requests\Api\V1\Compliance\FindingIndexRequest;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Http\JsonResponse;

class FindingController extends Controller
{
    use FiltersComplianceFindings;

    /**
     * List compliance findings with filtering.
     */
    public function index(FindingIndexRequest $request): JsonResponse
    {
        $query = ComplianceFinding::query();

        $this->applyFindingFilters($query, $request);

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
    public function dismiss(DismissFindingRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

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
        return response()->json([
            'success' => true,
            'data' => $this->getFindingStats(),
        ]);
    }
}
