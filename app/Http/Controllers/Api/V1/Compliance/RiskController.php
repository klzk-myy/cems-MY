<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CustomerRiskProfile;
use App\Services\Compliance\RiskScoringEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->firstOrFail();

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
    public function lock(Request $request, string $customerId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->firstOrFail();
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
        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->firstOrFail();
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
        $all = DB::select('SELECT risk_tier, COUNT(*) as count FROM customer_risk_profiles GROUP BY risk_tier');
        $byTier = [];
        foreach ($all as $row) {
            $byTier[$row->risk_tier] = (int) $row->count;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) DB::table('customer_risk_profiles')->count(),
                'by_tier' => $byTier,
            ],
        ]);
    }
}
