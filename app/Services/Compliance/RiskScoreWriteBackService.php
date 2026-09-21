<?php

namespace App\Services\Compliance;

use App\Enums\RiskRating;
use App\Models\Compliance\CustomerRiskHistory;
use App\Models\Customer;
use App\Support\ActorContext;

/**
 * Risk Score Write-Back Service
 *
 * Single post-compute hook shared by every risk scoring path
 * (RiskScoringEngine, CustomerRiskScoringService, periodic reviews) so the
 * customer record can never drift from its computed score:
 *
 * - Persists the new score/rating on the customer (quietly, to avoid
 *   event loops when called inside transactions or observers).
 * - Records a CustomerRiskHistory row whenever the score or rating changes.
 *   Callers run this inside their own DB transaction so snapshot creation
 *   and the history row commit atomically.
 */
class RiskScoreWriteBackService
{
    /**
     * Apply a freshly computed risk score to the customer.
     *
     * Returns true when the score or rating changed (and therefore a
     * history row was written), false when nothing changed.
     */
    public function apply(Customer $customer, int $newScore, string $trigger, ?int $assessedBy = null): bool
    {
        $previousScore = (int) ($customer->risk_score ?? 0);
        $previousRating = $this->normalizeRating($customer->risk_rating);
        $newRating = $this->ratingForScore($newScore);

        // A sanction hit pins the rating at High regardless of the numeric
        // score — without this, a sanctioned Malaysian customer scoring 50
        // would be written back as Medium, silently downgrading the rating
        // set by screening.
        if ($customer->sanction_hit) {
            $newRating = RiskRating::High;
        }

        if ($previousScore === $newScore && $previousRating === $newRating) {
            return false;
        }

        $customer->forceFill([
            'risk_score' => $newScore,
            'risk_rating' => $newRating->value,
            'risk_assessed_at' => now(),
        ])->saveQuietly();

        CustomerRiskHistory::create([
            'customer_id' => $customer->id,
            'old_score' => $previousScore,
            'new_score' => $newScore,
            'old_rating' => $previousRating->value,
            'new_rating' => $newRating->value,
            'change_reason' => $trigger,
            'assessed_by' => $assessedBy ?? ActorContext::capture()->userId,
        ]);

        return true;
    }

    /**
     * Normalize any stored rating value into the RiskRating enum.
     */
    protected function normalizeRating(mixed $rating): RiskRating
    {
        if ($rating instanceof RiskRating) {
            return $rating;
        }

        return RiskRating::tryFrom(strtolower((string) $rating)) ?? RiskRating::Low;
    }

    /**
     * Map a computed score to a storable rating.
     *
     * The customers.risk_rating and customer_risk_history.new_rating columns
     * are enums limited to Low/Medium/High, so Critical collapses to High at
     * the storage boundary.
     */
    protected function ratingForScore(int $score): RiskRating
    {
        return match (true) {
            $score >= 60 => RiskRating::High,
            $score >= 30 => RiskRating::Medium,
            default => RiskRating::Low,
        };
    }
}
