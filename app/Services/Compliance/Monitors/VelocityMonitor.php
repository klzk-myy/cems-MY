<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;

/**
 * Monitor for detecting customers exceeding transaction velocity thresholds.
 * Scans for customers exceeding RM 50,000 in 24-hour window.
 */
class VelocityMonitor extends BaseMonitor
{
    protected string $threshold;

    protected string $warningThreshold;

    public function __construct()
    {
        parent::__construct();
        $this->threshold = config('thresholds.velocity.alert_threshold', '50000');
        $this->warningThreshold = config('thresholds.velocity.warning_threshold', '45000');
    }

    public const LOOKBACK_HOURS = 24;

    protected function getFindingType(): FindingType
    {
        return FindingType::VelocityExceeded;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subHours(self::LOOKBACK_HOURS);

        $customerIds = Transaction::where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->distinct('customer_id')
            ->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $finding = $this->checkCustomerVelocity($customerId);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    protected function checkCustomerVelocity(int $customerId): ?array
    {
        $cutoffTime = now()->subHours(self::LOOKBACK_HOURS);

        $totalAmount = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->sum('amount_local');

        $transactionCount = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();

        if ($this->math->compare((string) $totalAmount, $this->threshold) >= 0) {
            $customer = Customer::find($customerId);

            return $this->createFinding(
                type: FindingType::VelocityExceeded,
                severity: FindingSeverity::High,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transactions_24h' => $transactionCount,
                    'total_amount_24h' => (string) $totalAmount,
                    'threshold' => $this->threshold,
                    'recommendation' => 'STR recommended if suspicious',
                ]
            );
        }

        if ($this->math->compare((string) $totalAmount, $this->warningThreshold) >= 0) {
            $customer = Customer::find($customerId);

            return $this->createFinding(
                type: FindingType::VelocityExceeded,
                severity: FindingSeverity::Medium,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transactions_24h' => $transactionCount,
                    'total_amount_24h' => (string) $totalAmount,
                    'threshold' => $this->warningThreshold,
                    'approaching_threshold' => true,
                ]
            );
        }

        return null;
    }
}
