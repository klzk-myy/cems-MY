<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctionController extends Controller
{
    public function __construct(
        protected UnifiedSanctionScreeningService $screeningService
    ) {}

    /**
     * Search sanctions list by name.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3',
        ]);

        $response = $this->screeningService->screenName($validated['name']);

        return response()->json([
            'success' => true,
            'query' => $validated['name'],
            'matches' => $response->matches->toArray(),
            'count' => $response->matches->count(),
            'action' => $response->action,
            'confidence_score' => $response->confidenceScore,
        ]);
    }

    /**
     * Upload sanctions list file.
     */
    public function upload(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Manual file upload is no longer supported. Sanctions lists are automatically imported from configured sources via scheduled jobs.',
        ], 410);
    }
}
