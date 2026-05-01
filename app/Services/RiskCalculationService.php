<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Risk\AmountRiskService;
use App\Services\Risk\GeographicRiskService;
use App\Services\Risk\PatternRiskService;
use App\Services\Risk\StructuringRiskService;
use App\Services\Risk\VelocityRiskService;

class RiskCalculationService
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected ?VelocityRiskService $velocityRiskService = null,
        protected ?StructuringRiskService $structuringRiskService = null,
        protected ?GeographicRiskService $geographicRiskService = null,
        protected ?AmountRiskService $amountRiskService = null,
        protected ?PatternRiskService $patternRiskService = null
    ) {}

    public function calculateVelocityRisk(int $customerId, int $windowHours = 24): int
    {
        if ($this->velocityRiskService) {
            return $this->velocityRiskService->calculateScore($customerId, $windowHours);
        }

        return $this->legacyCalculateVelocityRisk($customerId, $windowHours);
    }

    protected function legacyCalculateVelocityRisk(int $customerId, int $windowHours = 24): int
    {
        $score = 0;

        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $dailyAmounts = $transactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d'))
            ->map(fn ($day) => $day->sum('amount_local'));

        foreach ($dailyAmounts as $date => $amount) {
            $amountStr = (string) $amount;
            if ($this->mathService->compare($amountStr, $this->thresholdService->getRiskHighThreshold()) >= 0) {
                $score += 30;
            } elseif ($this->mathService->compare($amountStr, $this->thresholdService->getRiskMediumThreshold()) >= 0) {
                $score += 20;
            } elseif ($this->mathService->compare($amountStr, $this->thresholdService->getRiskLowThreshold()) >= 0) {
                $score += 10;
            }
        }

        return min($score, 40);
    }

    public function calculateStructuringRisk(int $customerId, int $windowHours = 1): int
    {
        if ($this->structuringRiskService) {
            return $this->structuringRiskService->calculateScore($customerId, $windowHours);
        }

        return $this->legacyCalculateStructuringRisk($customerId, $windowHours);
    }

    protected function legacyCalculateStructuringRisk(int $customerId, int $windowHours = 1): int
    {
        $score = 0;

        $subThreshold = $this->thresholdService->getStructuringSubThreshold();
        $window = now()->subHours($windowHours);

        $structuringTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        $hourlyGroups = $structuringTransactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d H'));

        foreach ($hourlyGroups as $hour => $txns) {
            if ($txns->count() >= 3) {
                $score += 25;
            } elseif ($txns->count() >= 2) {
                $score += 10;
            }
        }

        return min($score, 30);
    }

    public function calculateGeographicRisk(Customer $customer): int
    {
        return $this->geographicRiskService->calculateScore($customer);
    }

    public function calculateAmountRisk(int $customerId, ?string $currentAmount = null): int
    {
        $score = 0;

        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $customer = Customer::find($customerId);
        $score = $this->amountRiskService
            ? $this->amountRiskService->calculateScore($transactions, $customer)
            : $this->legacyCalculateAmountScore($transactions, $customer);

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

    protected function legacyCalculateAmountScore($transactions, Customer $customer): int
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

        return $score;
    }

    public function calculateCumulativeRisk(int $customerId, ?string $currentAmount = null): array
    {
        $cumulativeThreshold = $this->thresholdService->getVelocityAlertThreshold();
        $window = now()->subDays(7);

        $weekTotal = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->sum('amount_local');

        $weekTotalFormatted = number_format((float) $weekTotal, 2, '.', '');
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
        if ($this->patternRiskService) {
            return $this->patternRiskService->calculatePatternRisk($customerId);
        }

        return $this->legacyCalculatePatternRisk($customerId);
    }

    protected function legacyCalculatePatternRisk(int $customerId): array
    {
        $details = [];

        $recentTransactions = Transaction::where('customer_id', $customerId)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($recentTransactions->count() < 5) {
            return [
                'pattern_reversal' => false,
                'currency_switch' => false,
                'details' => [],
            ];
        }

        $buyCount = $recentTransactions->where('type', TransactionType::Buy)->count();
        $sellCount = $recentTransactions->where('type', TransactionType::Sell)->count();
        $patternReversal = false;

        if ($buyCount >= 7 && $sellCount >= 2) {
            $lastType = $recentTransactions->first()->type;
            $prevType = $recentTransactions->skip(1)->first()->type;

            if ($lastType === TransactionType::Sell && $prevType === TransactionType::Buy) {
                $patternReversal = true;
                $details[] = 'Pattern change: Previously buying, now selling';
            }
        }

        $currencies = $recentTransactions->pluck('currency_code')->unique();
        $currencySwitch = $currencies->count() >= 3;

        if ($currencySwitch) {
            $details[] = 'Multiple currency types in recent transactions';
        }

        return [
            'pattern_reversal' => $patternReversal,
            'currency_switch' => $currencySwitch,
            'details' => $details,
        ];
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
        if ($this->velocityRiskService) {
            return $this->velocityRiskService->checkThreshold($customerId, $windowHours, $threshold);
        }

        $count = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();

        return [
            'triggered' => $count >= $threshold,
            'count' => $count,
            'threshold' => $threshold,
        ];
    }

    public function checkStructuringThreshold(int $customerId, int $windowHours = 1, int $threshold = 3): array
    {
        if ($this->structuringRiskService) {
            return $this->structuringRiskService->checkThreshold($customerId, $windowHours, $threshold);
        }

        $subThreshold = $this->thresholdService->getStructuringSubThreshold();

        $count = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();

        return [
            'triggered' => $count >= $threshold,
            'count' => $count,
            'threshold' => $threshold,
        ];
    }

    public function checkVelocityAmountThreshold(int $customerId, string $newAmount): array
    {
        if ($this->velocityRiskService) {
            return $this->velocityRiskService->checkAmountThreshold($customerId, $newAmount);
        }

        $startTime = now()->subHours(24);
        $velocity = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $startTime)
            ->selectRaw('CAST(SUM(amount_local) AS CHAR) as total')
            ->value('total') ?? '0';

        $total = $this->mathService->add((string) $velocity, $newAmount);

        return [
            'amount_24h' => (string) $velocity,
            'with_new_transaction' => $total,
            'threshold_exceeded' => $this->mathService->compare($total, $this->thresholdService->getVelocityAlertThreshold()) >= 0,
            'threshold_amount' => $this->thresholdService->getVelocityAlertThreshold(),
        ];
    }

    public function isStructuring(int $customerId): bool
    {
        if ($this->structuringRiskService) {
            return $this->structuringRiskService->isStructuring($customerId);
        }

        $oneHourAgo = now()->subHour();
        $smallTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $oneHourAgo)
            ->where('amount_local', '<', $this->thresholdService->getStructuringSubThreshold())
            ->count();

        return $smallTransactions >= 3;
    }
}
