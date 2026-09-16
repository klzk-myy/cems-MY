<?php

namespace App\Services\Traits;

use App\Services\Transaction\ExchangeCalculator;

/**
 * Shared ExchangeCalculator resolution for transaction services.
 *
 * Removes duplicate resolveExchangeCalculator() methods across:
 * - TransactionCreationService
 * - TransactionImportService
 *
 * The property is declared without a default here so each composing class can
 * promote it in its constructor (PHP forbids a trait property from carrying
 * the same default as a promoted one).
 */
trait ExchangeCalculatorTrait
{
    protected ExchangeCalculator $exchangeCalculator;

    protected function resolveExchangeCalculator(): ExchangeCalculator
    {
        return $this->exchangeCalculator;
    }
}
