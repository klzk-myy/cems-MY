<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ComplianceService;
use App\Services\MathService;

/**
 * Monitor for detecting structuring patterns.
 * Detects 3+ transactions under RM 3,000 within 1 hour.
 */
class StructuringMonitor extends BaseMonitor
{
    protected string $subThreshold;

    protected ComplianceService $complianceService;

    public const STRUCTURING_COUNT = 3;

    public const LOOKBACK_MINUTES = 60;

    public function __construct(MathService $math, ComplianceService $complianceService)
    {
        parent::__construct($math);
        $this->subThreshold = config('thresholds.structuring.sub_threshold', '3000');
        $this->complianceService = $complianceService;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::StructuringPattern;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subMinutes(self::LOOKBACK_MINUTES);

        $customerIds = Transaction::where('created_at', '>=', $cutoffTime)
            ->where('amount_local', '<', $this->subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->distinct('customer_id')
            ->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $finding = $this->checkCustomerStructuring($customerId);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    protected function checkCustomerStructuring(int $customerId): ?array
    {
        $cutoffTime = now()->subMinutes(self::LOOKBACK_MINUTES);

        $smallTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $cutoffTime)
            ->where('amount_local', '<', $this->subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'desc')
            ->get();

        $transactionCount = $smallTransactions->count();

        // Delegate structuring check to ComplianceService
        $isStructuring = $this->complianceService->checkStructuring($customerId);

        if ($isStructuring) {
            $customer = Customer::find($customerId);
            $totalAmount = $smallTransactions->sum('amount_local');

            return $this->createFinding(
                type: FindingType::StructuringPattern,
                severity: FindingSeverity::High,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transaction_count' => $transactionCount,
                    'total_amount' => (string) $totalAmount,
                    'threshold' => $this->subThreshold,
                    'transaction_ids' => $smallTransactions->pluck('id')->toArray(),
                    'recommendation' => 'STR strongly recommended',
                ]
            );
        }

        return null;
    }
}
