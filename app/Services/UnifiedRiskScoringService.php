<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Enums\EddStatus;
use App\Events\RiskScoreCalculated;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnifiedRiskScoringService
{
    protected array $factorWeights = [
        'velocity' => 25,
        'structuring' => 25,
        'geographic' => 20,
        'amount' => 15,
        'pep' => 20,
        'sanctions' => 100,
        'edd_history' => 10,
        'document' => 10,
        'behavioral' => 15,
    ];

    public function __construct(
        protected MathService $mathService,
    ) {}

    public function calculateRiskScore(Customer $customer): array
    {
        $factors = $this->calculateAllFactors($customer);
        $totalScore = $this->calculateTotalScore($factors);
        $riskTier = $this->determineRiskTier($totalScore, $factors);
        $cddLevel = $this->determineCddLevel($riskTier, $customer);
        $eddRequired = $this->determineEddRequired($riskTier, $factors, $customer);

        $profile = [
            'total_score' => $totalScore,
            'risk_tier' => $riskTier,
            'factors' => $factors,
            'cdd_level' => $cddLevel,
            'edd_required' => $eddRequired,
            'customer_id' => $customer->id,
            'calculated_at' => now()->toIso8601String(),
        ];

        event(new RiskScoreCalculated($customer, $profile));

        return $profile;
    }

    protected function calculateAllFactors(Customer $customer): array
    {
        return [
            'velocity' => $this->calculateVelocityFactor($customer),
            'structuring' => $this->calculateStructuringFactor($customer),
            'geographic' => $this->calculateGeographicFactor($customer),
            'amount' => $this->calculateAmountFactor($customer),
            'pep' => $this->calculatePepFactor($customer),
            'sanctions' => $this->calculateSanctionsFactor($customer),
            'edd_history' => $this->calculateEddHistoryFactor($customer),
            'document' => $this->calculateDocumentFactor($customer),
            'behavioral' => $this->calculateBehavioralFactor($customer),
        ];
    }

    protected function calculateTotalScore(array $factors): int
    {
        $total = 0;
        foreach ($factors as $factorName => $factorValue) {
            $weight = $this->factorWeights[$factorName] ?? 0;
            $contribution = (int) (($factorValue / 100) * $weight);
            $total = $this->mathService->add((string) $total, (string) $contribution);
        }

        return min((int) $total, 100);
    }

    protected function determineRiskTier(int $score, array $factors): string
    {
        if ($factors['sanctions'] > 0) {
            return 'Critical';
        }

        if ($score >= 80) {
            return 'Critical';
        }
        if ($score >= 60) {
            return 'High';
        }
        if ($score >= 30) {
            return 'Medium';
        }

        return 'Low';
    }

    protected function determineCddLevel(string $riskTier, Customer $customer): CddLevel
    {
        if ($customer->pep_status || $riskTier === 'Critical') {
            return CddLevel::Enhanced;
        }

        if ($riskTier === 'High') {
            return CddLevel::Enhanced;
        }

        if ($riskTier === 'Medium') {
            return CddLevel::Standard;
        }

        return CddLevel::Simplified;
    }

    protected function determineEddRequired(string $riskTier, array $factors, Customer $customer): bool
    {
        if ($customer->pep_status) {
            return true;
        }

        if ($factors['sanctions'] > 0) {
            return true;
        }

        if ($riskTier === 'Critical' || $riskTier === 'High') {
            return true;
        }

        return false;
    }

    protected function calculateVelocityFactor(Customer $customer): int
    {
        $transactions = $this->getRecentTransactions($customer->id, 90);
        if ($transactions->isEmpty()) {
            return 0;
        }

        $dailyAmounts = $transactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d'))
            ->map(fn ($day) => $day->sum('amount_local'));

        $score = 0;
        foreach ($dailyAmounts as $date => $amount) {
            if ($this->mathService->compare((string) $amount, '50000') >= 0) {
                $score += 30;
            } elseif ($this->mathService->compare((string) $amount, '30000') >= 0) {
                $score += 20;
            } elseif ($this->mathService->compare((string) $amount, '10000') >= 0) {
                $score += 10;
            }
        }

        return min($score, 100);
    }

    protected function calculateStructuringFactor(Customer $customer): int
    {
        $transactions = $this->getRecentTransactions($customer->id, 7);
        if ($transactions->isEmpty()) {
            return 0;
        }

        $subThreshold = $transactions->filter(
            fn ($t) => $this->mathService->compare((string) $t->amount_local, '50000') < 0
        );

        $hourlyGroups = $subThreshold->groupBy(fn ($t) => $t->created_at->format('Y-m-d H'));
        $score = 0;

        foreach ($hourlyGroups as $hour => $txns) {
            if ($txns->count() >= 3) {
                $score += 25;
            } elseif ($txns->count() >= 2) {
                $score += 10;
            }
        }

        return min($score, 100);
    }

    protected function calculateGeographicFactor(Customer $customer): int
    {
        $score = 0;

        $highRiskCountries = DB::table('high_risk_countries')->pluck('country_code')->toArray();

        if (in_array($customer->nationality, $highRiskCountries)) {
            $score += 30;
        }

        return min($score, 100);
    }

    protected function calculateAmountFactor(Customer $customer): int
    {
        $transactions = $this->getRecentTransactions($customer->id, 90);
        if ($transactions->isEmpty()) {
            return 0;
        }

        $score = 0;
        $maxTransaction = (string) ($transactions->max('amount_local') ?? '0');

        if ($this->mathService->compare($maxTransaction, '50000') >= 0) {
            $score += 30;
        } elseif ($this->mathService->compare($maxTransaction, '30000') >= 0) {
            $score += 20;
        } elseif ($this->mathService->compare($maxTransaction, '10000') >= 0) {
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

        return min($score, 100);
    }

    protected function calculatePepFactor(Customer $customer): int
    {
        if ($customer->pep_status) {
            return 100;
        }

        $hasPepRelation = CustomerRelation::where('related_customer_id', $customer->id)
            ->where('relation_type', 'pep')
            ->where('status', 'active')
            ->exists();

        if ($hasPepRelation) {
            return 50;
        }

        $isPepAssociate = CustomerRelation::where('related_customer_id', $customer->id)
            ->where('relation_type', 'associate')
            ->where('status', 'active')
            ->exists();

        if ($isPepAssociate) {
            return 30;
        }

        return 0;
    }

    protected function calculateSanctionsFactor(Customer $customer): int
    {
        if ($customer->sanction_hit ?? false) {
            return 100;
        }

        $recentMatch = DB::table('screening_results')
            ->where('customer_id', $customer->id)
            ->where('result', 'block')
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        if ($recentMatch) {
            return 80;
        }

        return 0;
    }

    protected function calculateEddHistoryFactor(Customer $customer): int
    {
        $twelveMonthsAgo = now()->subMonths(12);
        $edd = EnhancedDiligenceRecord::where('customer_id', $customer->id)
            ->where('reviewed_at', '>=', $twelveMonthsAgo)
            ->first();

        if (! $edd) {
            return 0;
        }

        return match ($edd->status) {
            EddStatus::Rejected->value => 100,
            EddStatus::PendingReview->value => 50,
            EddStatus::Approved->value => 10,
            default => 0,
        };
    }

    protected function calculateDocumentFactor(Customer $customer): int
    {
        if (! method_exists($customer, 'documents')) {
            return 0;
        }

        $documents = $customer->documents ?? collect();
        $unverified = $documents->filter(fn ($doc) => ! ($doc->is_verified ?? false))->count();

        return min($unverified * 20, 100);
    }

    protected function calculateBehavioralFactor(Customer $customer): int
    {
        $deviationScore = 0;

        $baseline = DB::table('customer_behavioral_baselines')
            ->where('customer_id', $customer->id)
            ->first();

        if ($baseline) {
            $recentAvg = Transaction::where('customer_id', $customer->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->where('status', '!=', 'Cancelled')
                ->avg('amount_local');

            if ($recentAvg && $baseline->avg_transaction_size_myr > 0) {
                $ratio = $recentAvg / (float) $baseline->avg_transaction_size_myr;
                if ($ratio > 2.0) {
                    $deviationScore = 80;
                } elseif ($ratio > 1.5) {
                    $deviationScore = 50;
                } elseif ($ratio > 1.25) {
                    $deviationScore = 30;
                }
            }
        }

        $flaggedCount = FlaggedTransaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $flagScore = min($flaggedCount * 15, 100);

        return max($deviationScore, $flagScore);
    }

    protected function getRecentTransactions(int $customerId, int $days): Collection
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', 'Completed')
            ->get();
    }

    public function getFactorWeights(): array
    {
        return $this->factorWeights;
    }

    public function setFactorWeights(array $weights): void
    {
        $this->factorWeights = array_merge($this->factorWeights, $weights);
    }
}
