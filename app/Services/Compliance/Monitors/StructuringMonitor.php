<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Compliance\AlertTriageService;
use App\Services\Risk\StructuringRiskService;
use App\Services\System\MathService;
use App\Services\ThresholdService;

class StructuringMonitor extends BaseMonitor
{
    protected string $subThreshold;

    protected StructuringRiskService $structuringRiskService;

    protected int $minTransactions;

    public const LOOKBACK_MINUTES = 90;

    public function __construct(MathService $math, StructuringRiskService $structuringRiskService, ThresholdService $thresholdService, AlertTriageService $alertTriage)
    {
        parent::__construct($math, $alertTriage);
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

        try {
            $cutoffTime = now()->subMinutes(self::LOOKBACK_MINUTES);

            $customerData = Transaction::where('created_at', '>=', $cutoffTime)
                ->where('amount_myr', '<', $this->subThreshold)
                ->where('status', '!=', TransactionStatus::Cancelled->value)
                ->selectRaw('customer_id, COUNT(*) as transaction_count, CAST(SUM(amount_myr) AS CHAR) as total_amount_myr')
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) >= ?', [$this->minTransactions])
                ->get();

            $customerIds = $customerData->pluck('customer_id')->unique();
            $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

            foreach ($customerData as $data) {
                $finding = $this->createFindingFromData($data, $customers->get($data->customer_id));
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return $findings;
    }

    protected function createFindingFromData($data, ?Customer $customer): ?array
    {
        $customerId = $data->customer_id;
        $transactionCount = $data->transaction_count;
        $totalAmountMyr = (string) $data->total_amount_myr;

        if ($transactionCount >= $this->minTransactions) {
            return $this->createFinding(
                type: FindingType::StructuringPattern,
                severity: FindingSeverity::High,
                subjectType: 'Customer',
                subjectId: $customerId,
                details: [
                    'customer_name' => $customer->full_name ?? 'Unknown',
                    'transaction_count' => $transactionCount,
                    'total_amount_myr' => $totalAmountMyr,
                    'threshold' => $this->subThreshold,
                    'recommendation' => 'Escalate for review',
                ]
            );
        }

        return null;
    }
}
