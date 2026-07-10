<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\LockRiskProfileRequest;
use App\Models\Compliance\CustomerRiskProfile;
use App\Services\Compliance\RiskScoringEngine;
use Illuminate\Http\JsonResponse;

class RiskController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RiskScoringEngine $engine
    ) {}

    /**
     * Get risk profile for a customer.
     */
    public function show(string $customerId): JsonResponse
    {
        $profile = $this->findProfileOrFail($customerId);

        return $this->successResponse($profile, 'Risk profile retrieved successfully.');
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

        return $this->successResponse($history, 'Risk score history retrieved successfully.');
    }

    /**
     * Recalculate risk score for a customer.
     */
    public function recalculate(string $customerId): JsonResponse
    {
        $profile = $this->engine->recalculateForCustomer((int) $customerId);

        return $this->successResponse($profile, 'Risk score recalculated.');
    }

    /**
     * Lock a customer's risk profile.
     */
    public function lock(LockRiskProfileRequest $request, string $customerId): JsonResponse
    {
        $validated = $request->validated();

        $profile = $this->findProfileOrFail($customerId);

        $profile->lock(auth()->id(), $validated['reason']);

        return $this->successResponse($profile, 'Risk profile locked.');
    }

    /**
     * Unlock a customer's risk profile.
     */
    public function unlock(string $customerId): JsonResponse
    {
        $profile = $this->findProfileOrFail($customerId);

        $profile->unlock();

        return $this->successResponse($profile, 'Risk profile unlocked.');
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

        return $this->successResponse([
            'total' => CustomerRiskProfile::query()->count(),
            'by_tier' => $distribution,
        ], 'Risk portfolio overview retrieved successfully.');
    }

    private function findProfileOrFail(string $customerId): CustomerRiskProfile
    {
        $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->first();

        if (! $profile) {
            abort(404, 'Risk profile not found.');
        }

        return $profile;
    }
}
