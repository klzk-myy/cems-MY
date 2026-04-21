<?php

namespace App\Services;

use App\Models\Customer;
use App\ValueObjects\RiskAnalysisResult;
use Illuminate\Support\Facades\Log;

class HistoricalRiskAnalysisService
{
    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        protected ThresholdService $thresholdService,
        protected RiskCalculationService $riskCalculationService
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
        $check = $this->riskCalculationService->checkVelocityThreshold($customer->id, 24, 3);

        if ($check['triggered']) {
            $result->addFlag([
                'type' => 'velocity',
                'severity' => 'warning',
                'description' => "{$check['count']} transactions in last 24 hours",
                'metric' => $check['count'],
                'threshold' => $check['threshold'],
            ]);
        }
    }

    /**
     * Check structuring: Multiple transactions just below RM 3,000 threshold
     */
    private function checkStructuringRisk(Customer $customer, RiskAnalysisResult $result): void
    {
        $check = $this->riskCalculationService->checkStructuringThreshold($customer->id, 1, 2);

        if ($check['triggered']) {
            $result->addFlag([
                'type' => 'structuring',
                'severity' => 'critical',
                'description' => "Potential structuring: {$check['count']} transactions just below RM 3,000 threshold",
                'metric' => $check['count'],
                'threshold' => $check['threshold'],
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
        $riskScore = $this->riskCalculationService->getOverallRiskScore($customer->id, $currentAmount);

        // If amount score added a significant risk, flag it
        if ($riskScore['amount'] >= 20) {
            $result->addFlag([
                'type' => 'amount_escalation',
                'severity' => 'warning',
                'description' => 'Transaction amount significantly above 90-day average',
                'metric' => $riskScore['amount'],
                'threshold' => 20,
            ]);
        }
    }

    /**
     * Check pattern change: Buy/Sell reversal, currency switch
     */
    private function checkPatternChange(Customer $customer, RiskAnalysisResult $result): void
    {
        $patternRisk = $this->riskCalculationService->calculatePatternRisk($customer->id);

        if ($patternRisk['pattern_reversal']) {
            $result->addFlag([
                'type' => 'pattern_reversal',
                'severity' => 'warning',
                'description' => 'Pattern change: Previously buying, now selling',
                'metric' => 'buy_sell_reversal',
            ]);
        }

        if ($patternRisk['currency_switch']) {
            $result->addFlag([
                'type' => 'currency_switch',
                'severity' => 'info',
                'description' => 'Multiple currency types in recent transactions',
                'metric' => count($patternRisk['details']),
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
        $cumulative = $this->riskCalculationService->calculateCumulativeRisk($customer->id, $currentAmount);

        if ($cumulative['triggered']) {
            $result->addFlag([
                'type' => 'cumulative_amount',
                'severity' => 'warning',
                'description' => "7-day cumulative amount reaches RM {$cumulative['total']}",
                'metric' => $cumulative['total'],
                'threshold' => $cumulative['threshold'],
            ]);
        }
    }
}
