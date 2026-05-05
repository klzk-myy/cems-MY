<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Services\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CounterApiController extends Controller
{
    public function __construct(
        private CounterService $counterService
    ) {}

    public function close(Request $request, string $counterId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'closing_floats' => 'required|array',
            'closing_floats.*' => 'numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $counter = Counter::findOrFail($counterId);

        $session = $counter->sessions()
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No open session found for this counter',
            ], 404);
        }

        try {
            $result = $this->counterService->closeSession(
                $session,
                $request->user(),
                $request->input('closing_floats'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter closed successfully',
                'session' => $result['session'] ?? $session->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close counter: '.$e->getMessage(),
            ], 500);
        }
    }
}
