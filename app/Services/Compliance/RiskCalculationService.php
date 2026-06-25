<?php

namespace App\Services\Compliance;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Risk\AmountRiskService;
use App\Services\Risk\GeographicRiskService;
use App\Services\Risk\PatternRiskService;
use App\Services\Risk\StructuringRiskService;
use App\Services\Risk\VelocityRiskService;
use App\Services\System\MathService;
use App\Services\ThresholdService;

class RiskCalculationService
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected VelocityRiskService $velocityRiskService,
        protected StructuringRiskService $structuringRiskService,
        protected GeographicRiskService $geographicRiskService,
        protected AmountRiskService $amountRiskService,
        protected PatternRiskService $patternRiskService
    ) {}

    public function calculateVelocityRisk(int $customerId, int $windowHours = 24): int
    {
        return $this->velocityRiskService->calculateScore($customerId, $windowHours);
    }

    public function calculateStructuringRisk(int $customerId, int $windowHours = 1): int
    {
        return $this->structuringRiskService->calculateScore($customerId, $windowHours);
    }

    public function calculateGeographicRisk(Customer $customer): int
    {
        return $this->geographicRiskService->calculateScore($customer);
    }

    public function calculateAmountRisk(int $customerId, ?string $currentAmount = null): int
    {
        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $customer = Customer::find($customerId);
        $score = $this->amountRiskService->calculateScore($transactions, $customer);

        if ($currentAmount !== null) {
            $avgAmount = $transactions->avg('amount_local');
            if ($avgAmount > 0) {
                $avgAmountFormatted = number_format($avgAmount, 2, '.', '');
                $escalation = $this->mathService->divide($currentAmount, $avgAmountFormatted);
                if ($this->mathService->compare($escalation, '2.0') >= 0) {
                    $score += 10;
                }
            }
        }

        return min($score, 30);
    }

    public function calculateCumulativeRisk(int $customerId, ?string $currentAmount = null): array
    {
        $cumulativeThreshold = $this->thresholdService->getVelocityAlertThreshold();
        $window = now()->subDays(7);

        $weekTotal = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->sum('amount_local');

        $weekTotalFormatted = sprintf('%0.2f', $weekTotal);
        $currentAmountFormatted = $currentAmount ?? '0';
        $total = $this->mathService->add($weekTotalFormatted, $currentAmountFormatted);

        return [
            'triggered' => $this->mathService->compare($total, $cumulativeThreshold) >= 0,
            'total' => $total,
            'threshold' => $cumulativeThreshold,
        ];
    }

    public function calculatePatternRisk(int $customerId): array
    {
        return $this->patternRiskService->calculatePatternRisk($customerId);
    }

    public function getOverallRiskScore(int $customerId, ?string $currentAmount = null): array
    {
        $velocity = $this->calculateVelocityRisk($customerId);
        $structuring = $this->calculateStructuringRisk($customerId);
        $amount = $this->calculateAmountRisk($customerId, $currentAmount);
        $cumulative = $this->calculateCumulativeRisk($customerId, $currentAmount);
        $pattern = $this->calculatePatternRisk($customerId);

        $overall = $velocity + $structuring + $amount;

        if ($pattern['pattern_reversal']) {
            $overall += 10;
        }
        if ($pattern['currency_switch']) {
            $overall += 5;
        }

        return [
            'velocity' => $velocity,
            'structuring' => $structuring,
            'amount' => $amount,
            'cumulative' => $cumulative,
            'pattern' => $pattern,
            'overall' => min($overall, 100),
        ];
    }

    public function checkVelocityThreshold(int $customerId, int $windowHours = 24, int $threshold = 3): array
    {
        return $this->velocityRiskService->checkThreshold($customerId, $windowHours, $threshold);
    }

    public function checkStructuringThreshold(int $customerId, int $windowHours = 1, int $threshold = 3): array
    {
        return $this->structuringRiskService->checkThreshold($customerId, $windowHours, $threshold);
    }

    public function checkVelocityAmountThreshold(int $customerId, string $newAmount): array
    {
        return $this->velocityRiskService->checkAmountThreshold($customerId, $newAmount);
    }

    public function isStructuring(int $customerId): bool
    {
        return $this->structuringRiskService->isStructuring($customerId);
    }
}
