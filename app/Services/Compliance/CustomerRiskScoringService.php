<?php

namespace App\Services\Compliance;

use App\Enums\RiskRating;
use App\Enums\RiskTrend;
use App\Events\RiskScoreUpdated;
use App\Exceptions\Domain\RiskProfileNotFoundException;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\DTOs\PepCessationResult;
use App\Services\Risk\AmountRiskService;
use App\Services\Risk\GeographicRiskService;
use App\Services\Screening\CustomerScreeningService;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerRiskScoringService
{
    public function __construct(
        protected CustomerScreeningService $screeningService,
        protected AuditService $auditService,
        protected MathService $mathService,
        protected RiskCalculationService $riskCalculationService,
        protected PepAssessmentService $pepAssessmentService,
        protected GeographicRiskService $geographicRiskService,
        protected AmountRiskService $amountRiskService,
        protected RiskScoreWriteBackService $writeBack,
    ) {}

    /**
     * Calculate and store risk score snapshot for a customer.
     */
    public function calculateAndSnapshot(int $customerId, string $trigger = 'rescreen'): RiskScoreSnapshot
    {
        return DB::transaction(function () use ($customerId, $trigger) {
            $customer = Customer::findOrFail($customerId);

            $scores = $this->calculateRiskScores($customer);
            $previousSnapshots = $this->getRecentSnapshots($customerId);
            $previous = $previousSnapshots->first();
            $trend = RiskScoreSnapshot::calculateTrend($previousSnapshots->toArray());
            $factors = $this->extractRiskFactors($customer, $scores);

            $snapshot = RiskScoreSnapshot::create([
                'customer_id' => $customerId,
                'snapshot_date' => today(),
                'previous_score' => $previous?->overall_score,
                'previous_rating' => $previous?->overall_score !== null
                    ? $this->ratingForScore($previous->overall_score)
                    : null,
                'overall_score' => $scores['overall'],
                'overall_rating_label' => $this->ratingForScore($scores['overall'])->label(),
                'velocity_score' => $scores['velocity'],
                'structuring_score' => $scores['structuring'],
                'geographic_score' => $scores['geographic'],
                'amount_score' => $scores['amount'],
                'trend' => $trend,
                'factors' => $factors,
                'next_screening_date' => $this->calculateNextScreeningDate($scores['overall']),
            ]);

            // Shared post-compute hook: persist score/rating on the customer
            // and record history when changed - same transaction as the snapshot.
            $this->writeBack->apply($customer, $scores['overall'], $trigger);

            event(new RiskScoreUpdated($snapshot));

            return $snapshot;
        });
    }

    /**
     * Calculate all risk sub-scores for a customer.
     *
     * Overall mirrors RiskCalculationService::getOverallRiskScore: the sum of
     * transaction-driven sub-scores capped at 100. (It was previously
     * hardcoded to 0, which made every snapshot score - and any consumer of
     * it - meaningless.)
     */
    public function calculateRiskScores(Customer $customer): array
    {
        $transactions = $this->getRecentTransactions($customer->id);

        $scores = [
            'velocity' => $this->calculateVelocityScore($customer->id),
            'structuring' => $this->calculateStructuringScore($customer->id),
            'geographic' => $this->calculateGeographicScore($customer),
            'amount' => $this->calculateAmountScore($transactions, $customer),
        ];

        $scores['overall'] = min($scores['velocity'] + $scores['structuring'] + $scores['amount'], 100);

        return $scores;
    }

    /**
     * Perform full rescreening of a customer.
     *
     * Locked risk profiles (EDD review hold) are left untouched: no new
     * snapshot, no customer score write-back, no audit side-effects -
     * matching RiskScoringEngine::recalculateForCustomer semantics.
     */
    public function rescreenCustomer(int $customerId, string $trigger = 'rescreen'): array
    {
        $customer = Customer::findOrFail($customerId);

        $lockedProfile = CustomerRiskProfile::where('customer_id', $customerId)->first();
        if ($lockedProfile && $lockedProfile->isLocked()) {
            return [
                'customer_id' => $customerId,
                'locked' => true,
                'sanction_match' => false,
                'sanction_confidence' => 0.0,
                'previous_score' => null,
                'new_score' => (int) $customer->risk_score,
                'score_change' => 0,
                'significant_change' => false,
                'snapshot' => null,
            ];
        }

        $screeningResponse = $this->screeningService->screenCustomer($customer);

        $previousSnapshot = RiskScoreSnapshot::where('customer_id', $customerId)
            ->latest()
            ->first();

        $newSnapshot = $this->calculateAndSnapshot($customerId, $trigger);

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
                'old' => ['risk_level' => $oldLevel?->value],
                'new' => ['risk_level' => $newLevel?->value],
            ]);
        }

        return [
            'customer_id' => $customerId,
            'locked' => false,
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
                ->whereDate('snapshot_date', today());
        })->with('latestRiskSnapshot')->get();
    }

    /**
     * Get customers needing rescreening.
     *
     * The due check must run against each customer's LATEST snapshot:
     * whereHas('riskScoreSnapshots') would flag anyone with an old overdue
     * row even when their most recent screening set a future due date.
     */
    public function getCustomersNeedingRescreening(): Collection
    {
        return Customer::whereLatestSnapshotNeedsRescreening()
            ->with('latestRiskSnapshot')
            // Most overdue first; the subquery mirrors latestRiskSnapshot's
            // ordering (latest snapshot_date, greatest id on a tie).
            ->orderBy(
                RiskScoreSnapshot::select('next_screening_date')
                    ->whereColumn('customer_id', 'customers.id')
                    ->latest('snapshot_date')
                    ->latest('id')
                    ->limit(1)
            )
            ->get();
    }

    /**
     * Get risk trend for a customer over time.
     */
    public function getRiskTrend(int $customerId, int $months = 6): array
    {
        $snapshots = RiskScoreSnapshot::where('customer_id', $customerId)
            ->whereDate('snapshot_date', '>=', now()->subMonths($months))
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
        // One grouped aggregate instead of hydrating all of today's snapshots
        // and counting them in PHP. SUM(boolean) evaluates to 0/1 per row on
        // both MySQL and SQLite.
        $today = RiskScoreSnapshot::whereDate('snapshot_date', today())
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(overall_score >= 80) as critical,
                 SUM(overall_score BETWEEN 60 AND 79) as high,
                 SUM(overall_score BETWEEN 30 AND 59) as medium,
                 SUM(overall_score < 30) as low,
                 SUM(trend = ?) as deteriorating',
                [RiskTrend::Deteriorating->value]
            )
            ->first();

        // total/critical/… are selectRaw aliases, not model attributes — read
        // them through getAttribute() so static analysis stays honest. ->first()
        // returns null on an empty set, so guard with ?->.
        return [
            'total_scored_today' => (int) ($today?->getAttribute('total') ?? 0),
            'critical_risk' => (int) ($today?->getAttribute('critical') ?? 0),
            'high_risk' => (int) ($today?->getAttribute('high') ?? 0),
            'medium_risk' => (int) ($today?->getAttribute('medium') ?? 0),
            'low_risk' => (int) ($today?->getAttribute('low') ?? 0),
            'deteriorating_trend' => (int) ($today?->getAttribute('deteriorating') ?? 0),
            'needs_rescreening' => Customer::whereLatestSnapshotNeedsRescreening()->count(),
        ];
    }

    /**
     * Lock a customer's risk profile.
     */
    public function lockCustomerRisk(int $customerId, int $userId, string $reason): CustomerRiskProfile
    {
        $profile = CustomerRiskProfile::where('customer_id', $customerId)->first();

        if (! $profile) {
            throw new RiskProfileNotFoundException($customerId);
        }

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
            ->completed()
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
        $score = $this->amountRiskService->calculateScore($transactions, $customer);

        $monthlyVolume = (string) $transactions->where('created_at', '>=', now()->subDays(30))
            ->sum('amount_myr');

        if ($customer->annual_volume_myr) {
            $annualEstimate = (string) $customer->annual_volume_myr;
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

    protected function getRiskLevel(?int $score): ?RiskRating
    {
        return $score === null ? null : $this->ratingForScore($score);
    }

    /**
     * Map a risk score to the RiskRating enum used by the snapshot history
     * columns (previous_rating / overall_rating_label).
     */
    protected function ratingForScore(int $score): RiskRating
    {
        return match (true) {
            $score >= 80 => RiskRating::Critical,
            $score >= 60 => RiskRating::High,
            $score >= 30 => RiskRating::Medium,
            default => RiskRating::Low,
        };
    }

    protected function isRiskLevelHigher(?RiskRating $oldLevel, ?RiskRating $newLevel): bool
    {
        return ($newLevel?->weight() ?? 0) > ($oldLevel?->weight() ?? 0);
    }

    public function assessPepCessation(Customer $customer): PepCessationResult
    {
        return $this->pepAssessmentService->assessPepCessation($customer);
    }
}
