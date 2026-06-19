<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\LockRiskProfileRequest;
use App\Models\Compliance\CustomerRiskProfile;
use App\Services\Compliance\RiskScoringEngine;
use Illuminate\Http\JsonResponse;

class RiskController extends Controller
{
    public function __construct(
        protected RiskScoringEngine $engine
    ) {}

    /**
     * Get risk profile for a customer.
     */
    public function show(string $customerId): JsonResponse
    {
        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)
            ->with('customer')
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Risk profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Get risk score history for a customer.
     */
    public function history(string $customerId): JsonResponse
    {
        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->first();
        $history = [];

        if ($profile) {
            $history[] = [
                'score' => $profile->risk_score,
                'tier' => $profile->risk_tier,
                'changed_at' => $profile->score_changed_at?->toIso8601String(),
                'reason' => 'Current',
            ];
            if ($profile->previous_score) {
                $history[] = [
                    'score' => $profile->previous_score,
                    'tier' => CustomerRiskProfile::getTierForScore($profile->previous_score),
                    'changed_at' => $profile->score_changed_at?->subSecond()->toIso8601String(),
                    'reason' => 'Previous',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Recalculate risk score for a customer.
     */
    public function recalculate(string $customerId): JsonResponse
    {
        $profile = $this->engine->recalculateForCustomer((int) $customerId);

        return response()->json([
            'success' => true,
            'message' => 'Risk score recalculated.',
            'data' => $profile,
        ]);
    }

    /**
     * Lock a customer's risk profile.
     */
    public function lock(LockRiskProfileRequest $request, string $customerId): JsonResponse
    {
        $validated = $request->validated();

        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Risk profile not found.',
            ], 404);
        }

        $profile->lock(auth()->id(), $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Risk profile locked.',
            'data' => $profile,
        ]);
    }

    /**
     * Unlock a customer's risk profile.
     */
    public function unlock(string $customerId): JsonResponse
    {
        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Risk profile not found.',
            ], 404);
        }

        $profile->unlock();

        return response()->json([
            'success' => true,
            'message' => 'Risk profile unlocked.',
            'data' => $profile,
        ]);
    }

    /**
     * Get risk portfolio overview.
     */
    public function portfolio(): JsonResponse
    {
        $distribution = CustomerRiskProfile::query()
            ->selectRaw('risk_tier, COUNT(*) as count')
            ->groupBy('risk_tier')
            ->pluck('count', 'risk_tier')
            ->map(fn ($count) => (int) $count)
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => CustomerRiskProfile::query()->count(),
                'by_tier' => $distribution,
            ],
        ]);
    }
}
