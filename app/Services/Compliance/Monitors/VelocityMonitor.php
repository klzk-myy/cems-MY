<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\Risk\VelocityRiskService;
use App\Services\ThresholdService;

class VelocityMonitor extends BaseMonitor
{
    protected string $threshold;

    protected string $warningThreshold;

    protected VelocityRiskService $velocityRiskService;

    protected int $velocityWindowDays;

    public function __construct(MathService $math, VelocityRiskService $velocityRiskService, ThresholdService $thresholdService)
    {
        parent::__construct($math);
        $this->threshold = $thresholdService->getVelocityAlertThreshold();
        $this->warningThreshold = $thresholdService->getVelocityWarningThreshold();
        $this->velocityRiskService = $velocityRiskService;
        $this->velocityWindowDays = $thresholdService->getVelocityWindowDays();
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::VelocityExceeded;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subDays($this->velocityWindowDays);

        $customerData = Transaction::where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->selectRaw('customer_id, COUNT(*) as transaction_count, CAST(SUM(amount_local) AS CHAR) as total_amount')
            ->groupBy('customer_id')
            ->havingRaw('SUM(amount_local) >= ?', [$this->warningThreshold])
            ->get();

        foreach ($customerData as $data) {
            $finding = $this->createFindingFromData($data);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    protected function createFindingFromData($data): ?array
    {
        $customerId = $data->customer_id;
        $amount24h = (string) $data->total_amount;
        $transactionCount = $data->transaction_count;

        if ($this->math->compare($amount24h, $this->threshold) >= 0) {
            $customer = Customer::find($customerId);

            return $this->createFinding(
                type: FindingType::VelocityExceeded,
                severity: FindingSeverity::High,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transactions_in_window' => $transactionCount,
                    'total_amount_in_window' => $amount24h,
                    'window_days' => $this->velocityWindowDays,
                    'threshold' => $this->threshold,
                    'recommendation' => 'STR recommended if suspicious',
                ]
            );
        }

        if ($this->math->compare($amount24h, $this->warningThreshold) >= 0) {
            $customer = Customer::find($customerId);

            return $this->createFinding(
                type: FindingType::VelocityExceeded,
                severity: FindingSeverity::Medium,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transactions_in_window' => $transactionCount,
                    'total_amount_in_window' => $amount24h,
                    'window_days' => $this->velocityWindowDays,
                    'threshold' => $this->warningThreshold,
                    'approaching_threshold' => true,
                ]
            );
        }

        return null;
    }
}
