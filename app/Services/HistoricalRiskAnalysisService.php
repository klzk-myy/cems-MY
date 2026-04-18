<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HistoricalRiskAnalysisService
{
    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService
    ) {}

    /**
     * Analyze customer transaction history for risk patterns
     */
    public function analyze(Customer $customer, string $currentAmount): RiskAnalysisResult
    {
        $result = new RiskAnalysisResult;

        // Check various risk patterns
        $this->checkVelocityRisk($customer, $result);
        $this->checkStructuringRisk($customer, $result);
        $this->checkAmountEscalation($customer, $currentAmount, $result);
        $this->checkPatternChange($customer, $result);
        $this->checkCumulativeRisk($customer, $currentAmount, $result);

        // Log analysis
        if (count($result->getFlags()) > 0) {
            Log::info('Historical risk analysis completed with flags', [
                'customer_id' => $customer->id,
                'flags' => $result->getFlags(),
            ]);

            $this->auditService->logCustomerRiskEvent(
                'historical_risk_analysis',
                $customer->id,
                $result->getFlags()
            );
        }

        return $result;
    }

    /**
     * Check velocity: >3 transactions in 24h
     */
    private function checkVelocityRisk(Customer $customer, RiskAnalysisResult $result): void
    {
        $recentCount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($recentCount >= 3) {
            $result->addFlag([
                'type' => 'velocity',
                'severity' => 'warning',
                'description' => "{$recentCount} transactions in last 24 hours",
                'metric' => $recentCount,
                'threshold' => 3,
            ]);
        }
    }

    /**
     * Check structuring: Multiple transactions just below RM 3,000 threshold
     */
    private function checkStructuringRisk(Customer $customer, RiskAnalysisResult $result): void
    {
        $structuringThreshold = '3000';
        $structuringWindow = Carbon::now()->subHours(1);

        $structuringCount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', $structuringWindow)
            ->where('amount_local', '<', $structuringThreshold)
            ->where('amount_local', '>=', '2500')
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($structuringCount >= 2) {
            $result->addFlag([
                'type' => 'structuring',
                'severity' => 'critical',
                'description' => "Potential structuring: {$structuringCount} transactions just below RM 3,000 threshold",
                'metric' => $structuringCount,
                'threshold' => 2,
            ]);
        }
    }

    /**
     * Check amount escalation: 200% above 90-day average
     */
    private function checkAmountEscalation(
        Customer $customer,
        string $currentAmount,
        RiskAnalysisResult $result
    ): void {
        $avgAmount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->where('status', '!=', 'cancelled')
            ->avg('amount_local');

        if ($avgAmount > 0) {
            $escalation = $this->mathService->divide($currentAmount, (string) $avgAmount);

            if ($this->mathService->compare($escalation, '2.0') >= 0) {
                $result->addFlag([
                    'type' => 'amount_escalation',
                    'severity' => 'warning',
                    'description' => "Transaction amount is {$escalation}x above 90-day average",
                    'metric' => $escalation,
                    'threshold' => 2.0,
                ]);
            }
        }
    }

    /**
     * Check pattern change: Buy/Sell reversal, currency switch
     */
    private function checkPatternChange(Customer $customer, RiskAnalysisResult $result): void
    {
        // Get last 10 transactions
        $recentTransactions = Transaction::where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($recentTransactions->count() < 5) {
            return;
        }

        // Check for reversal (always buying, suddenly selling)
        $buyCount = $recentTransactions->where('type', TransactionType::Buy)->count();
        $sellCount = $recentTransactions->where('type', TransactionType::Sell)->count();

        if ($buyCount >= 7 && $sellCount >= 2) {
            // Previously mostly buying, now selling
            $lastType = $recentTransactions->first()->type;
            $prevType = $recentTransactions->skip(1)->first()->type;

            if ($lastType === TransactionType::Sell && $prevType === TransactionType::Buy) {
                $result->addFlag([
                    'type' => 'pattern_reversal',
                    'severity' => 'warning',
                    'description' => 'Pattern change: Previously buying, now selling',
                    'metric' => 'buy_sell_reversal',
                ]);
            }
        }

        // Check for currency switch (frequent currency changes)
        $currencies = $recentTransactions->pluck('currency_code')->unique();
        if ($currencies->count() >= 3) {
            $result->addFlag([
                'type' => 'currency_switch',
                'severity' => 'info',
                'description' => 'Multiple currency types in recent transactions',
                'metric' => $currencies->count(),
            ]);
        }
    }

    /**
     * Check cumulative: Aggregate related transactions over 7 days
     */
    private function checkCumulativeRisk(
        Customer $customer,
        string $currentAmount,
        RiskAnalysisResult $result
    ): void {
        $cumulativeThreshold = '50000';
        $window = Carbon::now()->subDays(7);

        $weekTotal = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', $window)
            ->where('status', '!=', 'cancelled')
            ->sum('amount_local');

        $total = $this->mathService->add((string) $weekTotal, $currentAmount);

        if ($this->mathService->compare($total, $cumulativeThreshold) >= 0) {
            $result->addFlag([
                'type' => 'cumulative_amount',
                'severity' => 'warning',
                'description' => "7-day cumulative amount reaches RM {$total}",
                'metric' => $total,
                'threshold' => $cumulativeThreshold,
            ]);
        }
    }
}
