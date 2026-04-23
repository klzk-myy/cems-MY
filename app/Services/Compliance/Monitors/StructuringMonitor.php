<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\Risk\StructuringRiskService;
use App\Services\ThresholdService;

class StructuringMonitor extends BaseMonitor
{
    protected string $subThreshold;

    protected StructuringRiskService $structuringRiskService;

    protected int $minTransactions;

    public const LOOKBACK_MINUTES = 60;

    public function __construct(MathService $math, StructuringRiskService $structuringRiskService, ThresholdService $thresholdService)
    {
        parent::__construct($math);
        $this->subThreshold = $thresholdService->getStructuringSubThreshold();
        $this->minTransactions = $thresholdService->getStructuringMinTransactions();
        $this->structuringRiskService = $structuringRiskService;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::StructuringPattern;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subMinutes(self::LOOKBACK_MINUTES);

        $customerData = Transaction::where('created_at', '>=', $cutoffTime)
            ->where('amount_local', '<', $this->subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->selectRaw('customer_id, COUNT(*) as transaction_count, CAST(SUM(amount_local) AS CHAR) as total_amount')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= ?', [$this->minTransactions])
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
        $transactionCount = $data->transaction_count;
        $totalAmount = (string) $data->total_amount;

        if ($transactionCount >= $this->minTransactions) {
            $customer = Customer::find($customerId);

            return $this->createFinding(
                type: FindingType::StructuringPattern,
                severity: FindingSeverity::High,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer?->full_name ?? 'Unknown',
                    'transaction_count' => $transactionCount,
                    'total_amount' => $totalAmount,
                    'threshold' => $this->subThreshold,
                    'recommendation' => 'STR strongly recommended',
                ]
            );
        }

        return null;
    }
}
