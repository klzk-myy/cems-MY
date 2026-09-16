<?php

namespace App\Services\Branch\DTOs;

use App\Support\BcmathHelper;

/**
 * Result of HandoverVarianceCalculator: per-currency variances, the
 * MYR-converted total, and the composed variance notes.
 */
final readonly class HandoverVarianceResult
{
    /**
     * @param  array<string, string>  $perCurrency  currency_code => signed variance
     */
    public function __construct(
        public array $perCurrency,
        public string $totalMyr,
        public string $notes,
    ) {}

    public function hasVariance(): bool
    {
        return BcmathHelper::isNotZero($this->totalMyr);
    }
}
