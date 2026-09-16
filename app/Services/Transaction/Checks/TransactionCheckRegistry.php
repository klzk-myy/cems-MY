<?php

namespace App\Services\Transaction\Checks;

/**
 * Ordered set of monitoring checks run by TransactionMonitoringService.
 * Order is load-bearing: downstream consumers may assert flag order.
 */
class TransactionCheckRegistry
{
    public function __construct(
        protected VelocityCheck $velocityCheck,
        protected StructuringCheck $structuringCheck,
        protected AggregateTransactionsCheck $aggregateTransactionsCheck,
        protected UnusualPatternCheck $unusualPatternCheck,
        protected HighRiskCountryCheck $highRiskCountryCheck,
        protected ProfileDeviationCheck $profileDeviationCheck,
        protected DurationOnHoldCheck $durationOnHoldCheck,
        protected HoldReasonCheck $holdReasonCheck,
    ) {}

    /**
     * @return array<int, TransactionCheck>
     */
    public function checks(): array
    {
        return [
            $this->velocityCheck,
            $this->structuringCheck,
            $this->aggregateTransactionsCheck,
            $this->unusualPatternCheck,
            $this->highRiskCountryCheck,
            $this->profileDeviationCheck,
            $this->durationOnHoldCheck,
            $this->holdReasonCheck,
        ];
    }
}
