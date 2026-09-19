<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\System\MathService;

/**
 * Customer's current-month volume exceeds twice the prorated annual estimate.
 */
class ProfileDeviationCheck implements TransactionCheck
{
    public function __construct(protected MathService $mathService) {}

    public function check(Transaction $transaction): array
    {
        if (! $this->isProfileDeviation($transaction)) {
            return [];
        }

        return [new FlagDescriptor(
            type: ComplianceFlagType::ProfileDeviation,
            reason: 'Transaction volume exceeds customer profile',
        )];
    }

    protected function isProfileDeviation(Transaction $transaction): bool
    {
        if (! $transaction->customer || ! $transaction->customer->annual_volume_myr) {
            return false;
        }

        $annualEstimate = (string) $transaction->customer->annual_volume_myr;

        if ($this->mathService->compare($annualEstimate, '0') <= 0) {
            return false;
        }

        $monthlyThreshold = $this->mathService->divide($annualEstimate, '12');
        $monthlyThreshold = $this->mathService->multiply($monthlyThreshold, '2');

        $startOfMonth = now()->startOfMonth();
        $currentMonthVolume = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', $startOfMonth)
            ->selectRaw('CAST(SUM(amount_myr) AS CHAR) as total')
            ->value('total') ?? '0';

        return $this->mathService->compare((string) $currentMonthVolume, $monthlyThreshold) > 0;
    }
}
