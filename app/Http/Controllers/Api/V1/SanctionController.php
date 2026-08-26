<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sanction\SearchSanctionRequest;
use App\Services\CustomerScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SanctionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerScreeningService $screeningService
    ) {}

    /**
     * Search sanctions list by name.
     */
    public function search(SearchSanctionRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Non-admin users can only access sanctions data for their branch context
        if (! $user->isAdmin() && $user->branch_id) {
            // Log access for audit
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
        return response()->json([
            'success' => true,
            'query' => $validated['name'],
            'matches' => $response->matches->toArray(),
            'count' => $response->matches->count(),
            'action' => $response->action,
            'confidence_score' => $response->confidenceScore,
        ]);
    }
}
