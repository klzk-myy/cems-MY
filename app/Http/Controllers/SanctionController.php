<?php

namespace App\Http\Controllers;

use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Http\Request;

class SanctionController extends Controller
{
    protected UnifiedSanctionScreeningService $screeningService;

    public function __construct(UnifiedSanctionScreeningService $screeningService)
    {
        $this->screeningService = $screeningService;
    }

    public function search(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3',
        ]);

        $response = $this->screeningService->screenName($request->name);

        return response()->json([
            'query' => $request->name,
            'matches' => $response->matches->toArray(),
            'count' => $response->matches->count(),
            'action' => $response->action,
            'confidence_score' => $response->confidenceScore,
        ]);
    }

    public function upload(Request $request)
    {
        return response()->json([
            'message' => 'Manual file upload is no longer supported. Sanctions lists are automatically imported from configured sources via scheduled jobs.',
            'supports_url_import' => true,
        ], 410);
    }
}
