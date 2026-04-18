<?php

namespace App\Services\Compliance;

use App\Enums\ComplianceFlagType;
use App\Enums\EddStatus;
use App\Enums\RecalculationTrigger;
use App\Enums\TransactionStatus;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\MathService;
use Illuminate\Support\Facades\DB;

/**
 * Risk Scoring Engine
 *
 * Calculates dynamic customer risk scores based on multiple factors including:
 * - Geographic risk
 * - PEP status
 * - Transaction deviation from baseline
 * - Velocity flags
 * - Structuring detection
 * - EDD history
 * - Document verification status
 * - Sanctions screening results
 */
class RiskScoringEngine
{
    protected MathService $math;

    /**
     * Base score for all customers.
     */
    protected const BASE_SCORE = 20;

    /**
     * Geographic risk score for high-risk countries.
     */
    protected const GEO_HIGH_RISK = 25;

    /**
     * Geographic risk score for regional (non-Malaysia, non-high-risk).
     */
    protected const GEO_REGIONAL = 10;

    public function __construct(MathService $math)
    {
        $this->math = $math;
    }

    /**
     * Calculate risk score for a customer.
     *
     * @return int Score (20-100)
     */
    public function calculateScore(int $customerId): int
    {
        $factors = $this->getFactorContributions($customerId);
        $total = self::BASE_SCORE;
        foreach ($factors as $factor) {
            $total = $this->math->add((string) $total, (string) $factor['contribution']);
        }

        return min((int) $total, 100);
    }

    /**
     * Calculate risk score with full factor breakdown.
     *
     * @return array{score: int, tier: string, factors: array}
     */
    public function calculateScoreWithFactors(int $customerId): array
    {
        $factors = $this->getFactorContributions($customerId);
        $total = self::BASE_SCORE;
        $factorDetails = [];
        foreach ($factors as $factor) {
            $total = $this->math->add((string) $total, (string) $factor['contribution']);
            $factorDetails[] = $factor;
        }
        $total = min((int) $total, 100);

        return [
            'score' => $total,
            'tier' => CustomerRiskProfile::getTierForScore($total),
            'factors' => $factorDetails,
        ];
    }

    /**
     * Recalculate and save risk profile for a customer.
     */
    public function recalculateForCustomer(int $customerId): CustomerRiskProfile
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::findOrFail($customerId);

            // Check if locked
            $existingProfile = CustomerRiskProfile::where('customer_id', $customerId)->first();
            if ($existingProfile && $existingProfile->isLocked()) {
                return $existingProfile;
            }

            $result = $this->calculateScoreWithFactors($customerId);

            if ($existingProfile) {
                $existingProfile->update([
                    'previous_score' => $existingProfile->risk_score,
                    'risk_score' => $result['score'],
                    'risk_tier' => $result['tier'],
                    'risk_factors' => $result['factors'],
                    'score_changed_at' => now(),
                    'recalculation_trigger' => RecalculationTrigger::EventDriven,
                ]);

                return $existingProfile->fresh();
            }

            return CustomerRiskProfile::createForCustomer($customerId, $result['score']);
        });
    }

    /**
     * Get factor contributions for a customer.
     */
    protected function getFactorContributions(int $customerId): array
    {
        $customer = Customer::with(['documents'])->find($customerId);
        if (! $customer) {
            return [];
        }

        $factors = [];

        // Geographic Risk
        $geoScore = $this->calculateGeographicRisk($customer);
        if ($geoScore > 0) {
            $factors[] = ['factor' => 'Geographic_Risk', 'contribution' => $geoScore];
        }

        // PEP Status
        $pepScore = $this->calculatePepRisk($customer);
        if ($pepScore > 0) {
            $factors[] = ['factor' => 'PEP_Status', 'contribution' => $pepScore];
        }

        // Transaction Deviation
        $deviation = $this->calculateTransactionDeviation($customerId);
        if ($deviation > 0) {
            $factors[] = ['factor' => 'Transaction_Deviation', 'contribution' => $deviation];
        }

        // Velocity Flags
        $velocityScore = $this->calculateVelocityScore($customerId);
        if ($velocityScore > 0) {
            $factors[] = ['factor' => 'Velocity_Flags', 'contribution' => $velocityScore];
        }

        // Structuring
        $structuringScore = $this->calculateStructuringScore($customerId);
        if ($structuringScore > 0) {
            $factors[] = ['factor' => 'Structuring', 'contribution' => $structuringScore];
        }

        // EDD History
        $eddScore = $this->calculateEddScore($customerId);
        if ($eddScore > 0) {
            $factors[] = ['factor' => 'EDD_History', 'contribution' => $eddScore];
        }

        // Document Status
        $docScore = $this->calculateDocumentScore($customer);
        if ($docScore > 0) {
            $factors[] = ['factor' => 'Document_Status', 'contribution' => $docScore];
        }

        // Sanctions
        $sanctionScore = $this->calculateSanctionScore($customer);
        if ($sanctionScore > 0) {
            $factors[] = ['factor' => 'Sanctions', 'contribution' => $sanctionScore];
        }

        return $factors;
    }

    /**
     * Calculate geographic risk score.
     */
    protected function calculateGeographicRisk(Customer $customer): int
    {
        // Check customer's nationality/risk rating
        $country = $customer->nationality ?? 'Malaysia';
        if ($country === 'Malaysia') {
            return 0;
        }

        // Check high risk countries table
        $isHighRisk = DB::table('high_risk_countries')
            ->where('country_code', $country)
            ->exists();

        if ($isHighRisk) {
            return self::GEO_HIGH_RISK;
        }

        return self::GEO_REGIONAL;
    }

    /**
     * Calculate PEP risk score.
     */
    protected function calculatePepRisk(Customer $customer): int
    {
        if ($customer->pep_status) {
            return 20;  // PEP
        }
        if ($customer->is_pep_associate ?? false) {
            return 15;  // PEP Associate
        }

        return 0;
    }

    /**
     * Calculate transaction deviation score.
     */
    protected function calculateTransactionDeviation(int $customerId): int
    {
        // Get baseline
        $baseline = CustomerBehavioralBaseline::where('customer_id', $customerId)->first();
        if (! $baseline) {
            return 0; // New customer, no baseline
        }

        // Calculate recent avg
        $recentAvg = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->avg('amount_local');

        if (! $recentAvg || $baseline->avg_transaction_size_myr == 0) {
            return 0;
        }

        $ratio = $this->math->divide((string) $recentAvg, (string) $baseline->avg_transaction_size_myr);
        $ratioFloat = (float) $ratio;

        if ($ratioFloat > 1.5) {
            return 20; // >50% above
        }
        if ($ratioFloat > 1.25) {
            return 10; // 25-50% above
        }
        if ($ratioFloat > 1.1) {
            return 5; // 10-25% above
        }

        return 0;
    }

    /**
     * Calculate velocity flag score.
     */
    protected function calculateVelocityScore(int $customerId): int
    {
        $ninetyDaysAgo = now()->subDays(90);
        $flagCount = FlaggedTransaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $ninetyDaysAgo)
            ->whereIn('flag_type', [ComplianceFlagType::Velocity->value])
            ->count();

        if ($flagCount >= 3) {
            return 20;
        }
        if ($flagCount >= 1) {
            return 10;
        }

        return 0;
    }

    /**
     * Calculate structuring detection score.
     */
    protected function calculateStructuringScore(int $customerId): int
    {
        $ninetyDaysAgo = now()->subDays(90);
        $structuringCount = FlaggedTransaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $ninetyDaysAgo)
            ->where('flag_type', ComplianceFlagType::Structuring->value)
            ->count();

        if ($structuringCount >= 2) {
            return 40;
        }
        if ($structuringCount >= 1) {
            return 25;
        }

        return 0;
    }

    /**
     * Calculate EDD history score.
     */
    protected function calculateEddScore(int $customerId): int
    {
        $twelveMonthsAgo = now()->subMonths(12);
        $edd = EnhancedDiligenceRecord::where('customer_id', $customerId)
            ->where('reviewed_at', '>=', $twelveMonthsAgo)
            ->first();

        if (! $edd) {
            return 0;
        }
        if ($edd->status === EddStatus::Rejected->value) {
            return 15;
        }
        if ($edd->status === EddStatus::Approved->value) {
            return 5;
        }

        return 0;
    }

    /**
     * Calculate document verification score.
     */
    protected function calculateDocumentScore(Customer $customer): int
    {
        $documents = $customer->documents ?? collect();
        $unverified = $documents->filter(fn ($doc) => ! $doc->isVerified())->count();

        return min($unverified * 5, 10); // Max 10
    }

    /**
     * Calculate sanctions screening score.
     */
    protected function calculateSanctionScore(Customer $customer): int
    {
        if ($customer->sanction_confirmed ?? false) {
            return 50;
        }
        if ($customer->sanction_possible ?? false) {
            return 30;
        }

        return 0;
    }
}
