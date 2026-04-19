<?php

namespace App\Services;

use App\Enums\RiskRating;
use App\Models\Customer;
use App\Models\CustomerRiskHistory;
use Illuminate\Support\Facades\DB;

class RiskRatingService
{
    protected array $riskFactors = [
        'pep_status' => 40,
        'high_risk_country' => 30,
        'complex_ownership' => 25,
        'cash_intensive' => 20,
        'unusual_pattern' => 10,
    ];

    public function __construct(
        protected ThresholdService $thresholdService
    ) {}

    public function calculateRiskScore(Customer $customer): int
    {
        $score = 0;

        // PEP status check
        if ($customer->pep_status) {
            $score += $this->riskFactors['pep_status'];
        }

        // High-risk country check
        if ($this->isHighRiskCountry($customer->nationality)) {
            $score += $this->riskFactors['high_risk_country'];
        }

        // Cash-intensive pattern check
        if ($this->isCashIntensive($customer->id)) {
            $score += $this->riskFactors['cash_intensive'];
        }

        return min($score, 100);
    }

    public function getRiskRating(int $score): string
    {
        if ($score <= 30) {
            return 'Low';
        }
        if ($score <= 60) {
            return 'Medium';
        }

        return 'High';
    }

    public function assessCustomer(Customer $customer, ?int $assessedBy = null): array
    {
        $oldScore = $customer->risk_score;
        $oldRating = $customer->risk_rating;

        $newScore = $this->calculateRiskScore($customer);
        $newRating = $this->getRiskRating($newScore);

        $customer->update([
            'risk_score' => $newScore,
            'risk_rating' => $newRating,
            'risk_assessed_at' => now(),
        ]);

        // Log the change
        CustomerRiskHistory::create([
            'customer_id' => $customer->id,
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'old_rating' => $oldRating,
            'new_rating' => $newRating,
            'change_reason' => 'Automated risk assessment',
            'assessed_by' => $assessedBy,
        ]);

        return [
            'score' => $newScore,
            'rating' => $newRating,
            'changed' => $oldScore !== $newScore,
        ];
    }

    protected function isHighRiskCountry(string $nationality): bool
    {
        return DB::table('high_risk_countries')
            ->where('country_name', $nationality)
            ->exists();
    }

    protected function isCashIntensive(int $customerId): bool
    {
        $thirtyDaysAgo = now()->subDays(30);
        $largeCashThreshold = $this->thresholdService->getRiskMediumThreshold();
        $largeCashCount = DB::table('transactions')
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('amount_local', '>', $largeCashThreshold)
            ->count();

        return $largeCashCount > 3;
    }

    public function getRefreshFrequency(RiskRating $rating): int
    {
        return match ($rating) {
            RiskRating::Low => 3, // 3 years
            RiskRating::Medium => 2, // 2 years
            RiskRating::High => 1, // 1 year
        };
    }
}
