<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Api\V1\Traits\LegacyApiResponse;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sanction\SearchSanctionRequest;
use App\Services\Screening\CustomerScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SanctionController extends Controller
{
    use ApiResponse;
    use LegacyApiResponse;
    use RequiresPermission;

    public function __construct(
        protected CustomerScreeningService $screeningService
    ) {}

    /**
     * Search sanctions list by name.
     *
     * Screening is a compliance capability: the web surface gates it behind
     * `role:access_compliance`, so the API requires the same permission instead
     * of accepting any authenticated token. Each call also persists a
     * screening result, so an open endpoint would let any role write rows.
     */
    public function search(SearchSanctionRequest $request): JsonResponse
    {
        $this->requirePermission(Permission::AccessCompliance);

        $user = auth()->user();

        if ($user && $user->branch_id) {
            Log::info('Sanctions search by branch user', [
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                'query' => $request->input('name'),
            ]);
        }

        $validated = $request->validated();

        $response = $this->screeningService->screenName($validated['name']);

        // Legacy non-standard envelope (query/matches/count/action/confidence_score);
        // preserved to avoid breaking API consumers.
        return $this->legacyJsonResponse([
            'success' => true,
            'query' => $validated['name'],
            'matches' => $response->matches->toArray(),
            'count' => $response->matches->count(),
            'action' => $response->action,
            'confidence_score' => $response->confidenceScore,
        ]);
    }
}
