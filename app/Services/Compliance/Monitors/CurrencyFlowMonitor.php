<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Models\Transaction;
use App\Services\Compliance\RoundTripDetector;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Monitor for detecting unusual currency round-tripping patterns.
 * Flags when the same currency goes out (Sell) and comes back in (Buy) within a short period.
 */
class CurrencyFlowMonitor extends BaseMonitor
{
    protected ThresholdService $thresholdService;

    protected RoundTripDetector $roundTripDetector;

    public const TIME_WINDOW_HOURS = 72;

    public function __construct(MathService $math, ThresholdService $thresholdService, RoundTripDetector $roundTripDetector)
    {
        parent::__construct($math);
        $this->thresholdService = $thresholdService;
        $this->roundTripDetector = $roundTripDetector;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::CurrencyFlowAnomaly;
    }

    public function run(): array
    {
        $findings = [];

        try {
            $cutoffTime = now()->subDays($this->thresholdService->getCurrencyFlowLookbackDays());

            $grouped = Transaction::with('customer')
                ->where('created_at', '>=', $cutoffTime)
                ->notCancelled()
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('customer_id');

            foreach ($grouped as $customerId => $recentTransactions) {
                $finding = $this->checkCustomerRoundTripping($customerId, $recentTransactions);
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

    /**
     * Check a customer for currency round-tripping patterns.
     */
    protected function checkCustomerRoundTripping(int $customerId, $recentTransactions): ?array
    {
        $cutoffTime = now()->subHours(self::TIME_WINDOW_HOURS);
        $recentTransactions = $recentTransactions->where('created_at', '>=', $cutoffTime);

        if ($recentTransactions->count() < 2) {
            return null;
        }

        // Group by currency to find round-trip patterns
        $roundTripPatterns = $this->detectRoundTrips($recentTransactions);

        if (empty($roundTripPatterns)) {
            return null;
        }

        $customer = $recentTransactions->first()->customer;

        return $this->createFinding(
            type: FindingType::CurrencyFlowAnomaly,
            severity: FindingSeverity::Low,
            subjectType: 'Customer',
            subjectId: $customerId,
            details: [
                'customer_name' => $customer->full_name ?? 'Unknown',
                'round_trip_count' => count($roundTripPatterns),
                'patterns' => $roundTripPatterns,
                'recommendation' => 'Review currency flow patterns for potential layering',
            ]
        );
    }

    /**
     * Detect round-trip patterns in transactions.
     *
     * @return array Array of detected round-trip patterns
     */
    protected function detectRoundTrips($transactions): array
    {
        return $this->roundTripDetector->detect(
            $transactions,
            self::TIME_WINDOW_HOURS,
            $this->thresholdService->getRoundTripThreshold()
        );
    }
}
