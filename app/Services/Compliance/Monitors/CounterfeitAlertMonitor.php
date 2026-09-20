<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\ComplianceFlagType;
use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\FlagStatus;
use App\Models\FlaggedTransaction;
use App\Services\Compliance\AlertTriageService;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use Illuminate\Support\Facades\Log;

/**
 * Monitor for detecting counterfeit currency alerts.
 * Checks for flagged transactions with counterfeit-related flag types.
 */
class CounterfeitAlertMonitor extends BaseMonitor
{
    public const LOOKBACK_DAYS = 30;

    protected SystemAlertService $alertService;

    public function __construct(MathService $math, SystemAlertService $alertService, AlertTriageService $alertTriage)
    {
        parent::__construct($math, $alertTriage);
        $this->alertService = $alertService;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::CounterfeitAlert;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subDays(self::LOOKBACK_DAYS);

        try {
            // Find flagged transactions with counterfeit currency flags
            $flaggedTransactions = FlaggedTransaction::where('created_at', '>=', $cutoffTime)
                ->where('flag_type', ComplianceFlagType::CounterfeitCurrency)
                ->whereIn('status', [FlagStatus::Open->value, FlagStatus::UnderReview->value])
                ->with(['customer', 'transaction'])
                ->get();

            foreach ($flaggedTransactions as $flag) {
                $finding = $this->processCounterfeitFlag($flag);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        } catch (\Throwable $e) {
            // Propagate to MonitoringEngine — it records the failure, trips
            // the circuit breaker and alerts compliance officers. A monitor
            // that cannot run must never report a clean sweep.
            Log::error('CounterfeitAlertMonitor run failed', ['exception' => $e->getMessage()]);

            throw $e;
        }

        return $findings;
    }

    /**
     * Process a counterfeit flag and generate a finding.
     */
    protected function processCounterfeitFlag(FlaggedTransaction $flag): ?array
    {
        // Only process unresolved counterfeit flags
        if ($flag->status->value !== FlagStatus::Open->value && $flag->status->value !== FlagStatus::UnderReview->value) {
            return null;
        }

        $customer = $flag->customer;
        $transaction = $flag->transaction;

        return $this->createFinding(
            type: FindingType::CounterfeitAlert,
            severity: FindingSeverity::Critical,
            subjectType: 'Transaction',
            subjectId: $flag->transaction_id ?? 0,
            details: [
                'flag_id' => $flag->id,
                'customer_id' => $flag->customer_id,
                'customer_name' => $customer->full_name ?? 'Unknown',
                'transaction_id' => $flag->transaction_id,
                'transaction_amount' => $transaction?->amount_myr ? (string) $transaction->amount_myr : 'Unknown',
                'currency_code' => $transaction->currency_code ?? 'Unknown',
                'flag_reason' => $flag->flag_reason ?? 'Counterfeit currency reported',
                'flag_created_at' => $flag->created_at?->toDateTimeString(),
                'status' => $flag->status->value,
                'recommendation' => 'Immediate confiscation and BNM reporting required',
            ]
        );
    }
}
