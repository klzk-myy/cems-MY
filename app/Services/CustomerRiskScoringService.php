<?php

namespace App\Services;

use App\Enums\RiskTrend;
use App\Enums\TransactionStatus;
use App\Events\RiskScoreUpdated;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\Transaction;
use App\Services\Risk\GeographicRiskService;
use Illuminate\Support\Collection;

class CustomerRiskScoringService
{
    public function __construct(
        protected CustomerScreeningService $screeningService,
        protected ComplianceService $complianceService,
        protected AuditService $auditService,
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected RiskCalculationService $riskCalculationService,
        protected ?GeographicRiskService $geographicRiskService = null,
    ) {}

    /**
     * Calculate and store risk score snapshot for a customer.
     */
    public function calculateAndSnapshot(int $customerId): RiskScoreSnapshot
    {
        $customer = Customer::findOrFail($customerId);

        $scores = $this->calculateRiskScores($customer);
        $previousSnapshots = $this->getRecentSnapshots($customerId);
        $trend = RiskScoreSnapshot::calculateTrend($previousSnapshots->toArray());
        $factors = $this->extractRiskFactors($customer, $scores);

        $snapshot = RiskScoreSnapshot::create([
            'customer_id' => $customerId,
            'snapshot_date' => today(),
            'overall_score' => $scores['overall'],
            'velocity_score' => $scores['velocity'],
            'structuring_score' => $scores['structuring'],
            'geographic_score' => $scores['geographic'],
            'amount_score' => $scores['amount'],
            'trend' => $trend,
            'factors' => $factors,
            'next_screening_date' => $this->calculateNextScreeningDate($scores['overall']),
        ]);

        event(new RiskScoreUpdated($snapshot));

        return $snapshot;
    }

    /**
     * Calculate all risk sub-scores for a customer.
     */
    public function calculateRiskScores(Customer $customer): array
    {
        $transactions = $this->getRecentTransactions($customer->id);

        return [
            'velocity' => $this->calculateVelocityScore($customer->id),
            'structuring' => $this->calculateStructuringScore($customer->id),
            'geographic' => $this->calculateGeographicScore($customer),
            'amount' => $this->calculateAmountScore($transactions, $customer),
            'overall' => 0,
        ];
    }

    /**
     * Perform full rescreening of a customer.
     */
    public function rescreenCustomer(int $customerId): array
    {
        $customer = Customer::findOrFail($customerId);

        $screeningResponse = $this->screeningService->screenCustomer($customer);

        $previousSnapshot = RiskScoreSnapshot::where('customer_id', $customerId)
            ->latest()
            ->first();

        $newSnapshot = $this->calculateAndSnapshot($customerId);

        $scoreChange = $previousSnapshot
            ? abs($newSnapshot->overall_score - $previousSnapshot->overall_score)
            : $newSnapshot->overall_score;

        // Log score change
        $this->auditService->logCustomerRiskEvent('customer_risk_score_changed', $customerId, [
            'old' => ['score' => $previousSnapshot?->overall_score],
            'new' => ['score' => $newSnapshot->overall_score],
        ]);

        // Log risk level upgrade (e.g., low->medium, medium->high, high->critical)
        $oldLevel = $this->getRiskLevel($previousSnapshot?->overall_score);
        $newLevel = $this->getRiskLevel($newSnapshot->overall_score);
        if ($this->isRiskLevelHigher($oldLevel, $newLevel)) {
            $this->auditService->logCustomerRiskEvent('customer_risk_level_upgraded', $customerId, [
                'old' => ['risk_level' => $oldLevel],
                'new' => ['risk_level' => $newLevel],
            ]);
        }

        return [
            'customer_id' => $customerId,
            'sanction_match' => $screeningResponse->action !== 'clear',
            'sanction_confidence' => $screeningResponse->confidenceScore,
            'previous_score' => $previousSnapshot?->overall_score,
            'new_score' => $newSnapshot->overall_score,
            'score_change' => $scoreChange,
            'significant_change' => $scoreChange >= 20,
            'snapshot' => $newSnapshot,
        ];
    }

    /**
     * Get high-risk customers needing attention.
     */
    public function getHighRiskCustomers(int $threshold = 60): Collection
    {
        return Customer::whereHas('riskScoreSnapshots', function ($query) use ($threshold) {
            $query->where('overall_score', '>=', $threshold)
                ->where('snapshot_date', today());
        })->with('latestRiskSnapshot')->get();
    }

    /**
     * Get customers needing rescreening.
     */
    public function getCustomersNeedingRescreening(): Collection
    {
        return Customer::whereHas('riskScoreSnapshots', function ($query) {
            $query->needsRescreening();
        })->with('latestRiskSnapshot')->get();
    }

    /**
     * Get risk trend for a customer over time.
     */
    public function getRiskTrend(int $customerId, int $months = 6): array
    {
        $snapshots = RiskScoreSnapshot::where('customer_id', $customerId)
            ->where('snapshot_date', '>=', now()->subMonths($months))
            ->orderBy('snapshot_date')
            ->get();

        return [
            'customer_id' => $customerId,
            'period' => $months.' months',
            'data_points' => $snapshots->count(),
            'current_score' => $snapshots->last()?->overall_score,
            'trend' => $snapshots->last()?->trend,
            'snapshots' => $snapshots->map(fn ($s) => [
                'date' => $s->snapshot_date->toDateString(),
                'score' => $s->overall_score,
                'trend' => $s->trend->value,
            ])->toArray(),
        ];
    }

    /**
     * Get dashboard summary statistics.
     */
    public function getDashboardSummary(): array
    {
        $todaySnapshots = RiskScoreSnapshot::where('snapshot_date', today())->get();

        return [
            'total_scored_today' => $todaySnapshots->count(),
            'critical_risk' => $todaySnapshots->where('overall_score', '>=', 80)->count(),
            'high_risk' => $todaySnapshots->whereBetween('overall_score', [60, 79])->count() + $todaySnapshots->where('overall_score', '>=', 80)->count(),
            'medium_risk' => $todaySnapshots->whereBetween('overall_score', [30, 59])->count(),
            'low_risk' => $todaySnapshots->where('overall_score', '<', 30)->count(),
            'deteriorating_trend' => $todaySnapshots->where('trend', RiskTrend::Deteriorating)->count(),
            'needs_rescreening' => $this->getCustomersNeedingRescreening()->count(),
        ];
    }

    /**
     * Lock a customer's risk profile.
     */
    public function lockCustomerRisk(int $customerId, int $userId, string $reason): CustomerRiskProfile
    {
        $profile = CustomerRiskProfile::where('customer_id', $customerId)->firstOrFail();

        $profile->lock($userId, $reason);

        $this->auditService->logCustomerRiskEvent('customer_risk_locked', $customerId, [
            'locked_by' => $userId,
            'reason' => $reason,
        ]);

        return $profile;
    }

    protected function getRecentTransactions(int $customerId): Collection
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', TransactionStatus::Completed->value)
            ->get();
    }

    protected function getRecentSnapshots(int $customerId): Collection
    {
        return RiskScoreSnapshot::where('customer_id', $customerId)
            ->latest()
            ->take(3)
            ->get();
    }

    protected function calculateVelocityScore(int $customerId): int
    {
        return $this->riskCalculationService->calculateVelocityRisk($customerId);
    }

    protected function calculateStructuringScore(int $customerId): int
    {
        return $this->riskCalculationService->calculateStructuringRisk($customerId);
    }

    protected function calculateGeographicScore(Customer $customer): int
    {
        return $this->geographicRiskService->calculateScore($customer);
    }

    protected function calculateAmountScore(Collection $transactions, Customer $customer): int
    {
        $score = 0;

        $maxTransaction = (string) ($transactions->max('amount_local') ?? '0');

        if ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskHighThreshold()) >= 0) {
            $score += 30;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskMediumThreshold()) >= 0) {
            $score += 20;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskLowThreshold()) >= 0) {
            $score += 10;
        }

        $monthlyVolume = (string) $transactions->where('created_at', '>=', now()->subDays(30))
            ->sum('amount_local');

        if ($customer->annual_volume_estimate) {
            $annualEstimate = (string) $customer->annual_volume_estimate;
            $expectedMonthly = $this->mathService->divide($annualEstimate, '12', 2);
            $threshold = $this->mathService->multiply($expectedMonthly, '2', 2);
            if ($this->mathService->compare($monthlyVolume, $threshold) > 0) {
                $score += 10;
            }
        }

        return min($score, 30);
    }

    protected function extractRiskFactors(Customer $customer, array $scores): array
    {
        $factors = [];

        if ($scores['velocity'] >= 20) {
            $factors[] = 'High velocity transactions detected';
        }

        if ($scores['structuring'] >= 15) {
            $factors[] = 'Potential structuring patterns identified';
        }

        if ($scores['geographic'] >= 20) {
            $factors[] = 'High-risk country involvement';
        }

        if ($scores['amount'] >= 20) {
            $factors[] = 'Large transaction amounts';
        }

        if ($customer->pep_status) {
            $factors[] = 'PEP customer';
        }

        if ($customer->is_sanctioned) {
            $factors[] = 'Sanctions match';
        }

        return $factors;
    }

    protected function calculateNextScreeningDate(int $overallScore): \DateTime
    {
        $days = match (true) {
            $overallScore >= 80 => 30,
            $overallScore >= 60 => 60,
            $overallScore >= 30 => 90,
            default => 180,
        };

        return now()->addDays($days);
    }

    protected function getRiskLevel(?int $score): string
    {
        if ($score === null) {
            return 'Unknown';
        }

        return match (true) {
            $score >= 80 => 'Critical',
            $score >= 60 => 'High',
            $score >= 30 => 'Medium',
            default => 'Low',
        };
    }

    protected function isRiskLevelHigher(string $oldLevel, string $newLevel): bool
    {
        $levels = ['Unknown' => 0, 'Low' => 1, 'Medium' => 2, 'High' => 3, 'Critical' => 4];

        return ($levels[$newLevel] ?? 0) > ($levels[$oldLevel] ?? 0);
    }
}
