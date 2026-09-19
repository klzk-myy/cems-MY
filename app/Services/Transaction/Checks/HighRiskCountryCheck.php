<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\HighRiskCountry;
use App\Models\Transaction;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Customer nationality is on the high-risk country list and the amount meets
 * the standard CDD threshold.
 */
class HighRiskCountryCheck implements TransactionCheck
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
    ) {}

    public function check(Transaction $transaction): array
    {
        if (! $transaction->customer || ! $transaction->customer->nationality) {
            return [];
        }

        if ($this->mathService->compare($transaction->amount_myr, $this->thresholdService->getStandardCddThreshold()) < 0) {
            return [];
        }

        if (! in_array($transaction->customer->nationality, HighRiskCountry::countryCodes(), true)) {
            return [];
        }

        return [new FlagDescriptor(
            type: ComplianceFlagType::HighRiskCountry,
            reason: 'High-risk country transaction: '.$transaction->customer->nationality,
        )];
    }
}
