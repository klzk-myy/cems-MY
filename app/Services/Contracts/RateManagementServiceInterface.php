<?php

namespace App\Services\Contracts;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\DTOs\RateOverrideResult;
use Illuminate\Support\Collection;

interface RateManagementServiceInterface
{
    public function fetchAndStoreRates(?User $initiatedBy = null, ?int $branchId = null): array;

    public function getCurrentRates(?int $branchId = null): Collection;

    public function getRateForCurrency(string $currencyCode, ?int $branchId = null): ?ExchangeRate;

    public function overrideRate(
        string $currencyCode,
        string $newBuyRate,
        string $newSellRate,
        User $approvedBy,
        ?string $reason = null,
        ?int $branchId = null
    ): RateOverrideResult;

    public function validateTransactionRate(
        string $submittedRate,
        string $currencyCode,
        string $transactionType = 'buy',
        ?int $branchId = null
    ): array;

    public function hasRateForCurrency(string $currencyCode, ?int $branchId = null): bool;

    public function areAllRatesSet(array $currencyCodes, ?int $branchId = null): array;

    public function getRatesSummary(?int $branchId = null): array;

    public function copyPreviousRates(string $targetDate, ?int $branchId = null): array;
}
