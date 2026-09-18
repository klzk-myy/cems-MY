<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Exceptions\Domain\CaseManagementException;
use App\Http\Concerns\FiltersComplianceFindings;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\DismissFindingRequest;
use App\Http\Requests\Api\V1\Compliance\FindingIndexRequest;
use App\Http\Resources\Api\V1\FindingResource;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Http\JsonResponse;

class FindingController extends Controller
{
    use ApiResponse;
    use FiltersComplianceFindings;

    /**
     * List compliance findings with filtering.
     */
    public function index(FindingIndexRequest $request): JsonResponse
    {
        $query = ComplianceFinding::query();

        $this->applyFindingFilters($query, $request);

        $perPage = min(100, max(1, (int) ($request->validated()['per_page'] ?? 20)));
        $findings = $query->orderBy('generated_at', 'desc')->paginate($perPage);

        return $this->resourceResponse(FindingResource::collection($findings), 'Findings retrieved successfully.');
    }

    /**
     * Get a specific finding.
     */
    public function show(int $id): JsonResponse
    {
        $finding = ComplianceFinding::with('subject')->findOrFail($id);

        return $this->successResponse(new FindingResource($finding), 'Finding retrieved successfully.');
    }

    /**
     * Dismiss a finding.
     */
    public function dismiss(DismissFindingRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $finding = ComplianceFinding::findOrFail($id);

        try {
            $finding->dismiss($validated['reason']);
        } catch (CaseManagementException $e) {
            return $this->domainErrorResponse($e, 'Failed to dismiss finding. Please try again.');
        }

        return $this->successResponse(new FindingResource($finding), 'Finding dismissed.');
    }

    /**
     * Get finding statistics.
     */
    public function stats(): JsonResponse
    {
        return $this->successResponse($this->getFindingStats(), 'Finding statistics retrieved successfully.');
    }
}
