<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ThresholdService;

/**
 * Monitor for detecting transactions in locations far from customer's registered address.
 * Flags anomalous location patterns based on customer's registered nationality.
 */
class CustomerLocationAnomalyMonitor extends BaseMonitor
{
    protected string $highValueThreshold;

    public const LOOKBACK_DAYS = 7;

    public function __construct(ThresholdService $thresholdService)
    {
        parent::__construct();
        $this->highValueThreshold = $thresholdService->getLargeTransactionThreshold();
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::LocationAnomaly;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subDays(self::LOOKBACK_DAYS);

        // Get customers with foreign nationality who have recent high-value transactions
        $foreignCustomers = Customer::where('is_active', true)
            ->where('nationality', '!=', 'Malaysian')
            ->where('nationality', '!=', 'Malaysia')
            ->get();

        foreach ($foreignCustomers as $customer) {
            $finding = $this->checkCustomerLocationAnomaly($customer, $cutoffTime);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Check a customer for location anomalies.
     */
    protected function checkCustomerLocationAnomaly(Customer $customer, $cutoffTime): ?array
    {
        // Get recent high-value transactions for this customer
        $recentTransactions = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->where('amount_local', '>=', $this->highValueThreshold)
            ->get();

        if ($recentTransactions->isEmpty()) {
            return null;
        }

        // Check for unusual patterns:
        // 1. Multiple different currencies in short period (suggesting travel)
        // 2. Very high amounts compared to customer's annual volume estimate
        $currencies = $recentTransactions->pluck('currency_code')->unique();
        $totalAmount = $recentTransactions->sum('amount_local');
        $transactionCount = $recentTransactions->count();

        $anomalyDetected = false;
        $anomalyReasons = [];

        // Multiple currencies in short period suggests location changes
        if ($currencies->count() >= 3) {
            $anomalyDetected = true;
            $anomalyReasons[] = 'Multiple currencies ('.$currencies->implode(', ').') in '.self::LOOKBACK_DAYS.' days';
        }

        // Check against annual volume estimate if available
        $annualEstimate = $customer->annual_volume_estimate;
        if ($annualEstimate !== null) {
            $weeklyEstimate = $this->math->divide((string) $annualEstimate, '52');
            $lookbackProportion = $this->math->divide((string) self::LOOKBACK_DAYS, '7');
            $expectedWeekly = $this->math->multiply($weeklyEstimate, $lookbackProportion);

            if ($this->math->compare($totalAmount, $expectedWeekly) > 0) {
                $anomalyDetected = true;
                $anomalyReasons[] = 'Transaction volume exceeds proportional annual estimate';
            }
        }

        // High frequency of transactions suggesting travel between locations
        if ($transactionCount >= 5) {
            $anomalyDetected = true;
            $anomalyReasons[] = 'High transaction frequency ('.$transactionCount.') in '.self::LOOKBACK_DAYS.' days';
        }

        if (! $anomalyDetected) {
            return null;
        }

        return $this->createFinding(
            type: FindingType::LocationAnomaly,
            severity: FindingSeverity::Low,
            subjectType: 'Customer',
            subjectId: $customer->id,
            details: [
                'customer_name' => $customer->full_name,
                'customer_nationality' => $customer->nationality,
                'transaction_count' => $transactionCount,
                'total_amount' => (string) $totalAmount,
                'currencies_used' => $currencies->toArray(),
                'anomaly_reasons' => $anomalyReasons,
                'recommendation' => 'Verify customer location if unusual travel pattern confirmed',
            ]
        );
    }
}
