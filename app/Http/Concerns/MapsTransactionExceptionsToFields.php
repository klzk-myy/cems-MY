<?php

namespace App\Http\Concerns;

use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\CurrencyNotFoundException;
use App\Exceptions\Domain\CustomerBlockedException;
use App\Exceptions\Domain\CustomerNotFoundException;
use App\Exceptions\Domain\InsufficientAllocationBalanceException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidAllocationStateException;
use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\InvalidRateException;
use App\Exceptions\Domain\KycExpiredException;
use App\Exceptions\Domain\NoActiveCounterSessionException;
use App\Exceptions\Domain\PendingAllocationNotFoundException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Exceptions\Domain\PositionLimitExceededException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Exceptions\Domain\TillClosedException;
use App\Exceptions\Domain\TransactionValidationException;

/**
 * Maps transaction-creation domain exceptions to the form field responsible
 * for the failure, so errors render under the correct input instead of a
 * generic "validation failed" banner. Shared by the web store, the
 * transaction wizard, and the API v1 endpoint.
 */
trait MapsTransactionExceptionsToFields
{
    /**
     * Return the transaction form field name for a domain exception, or
     * null when no specific field is responsible (the caller should fall
     * back to a general error banner carrying the exception message).
     */
    protected function transactionExceptionField(\Throwable $e): ?string
    {
        return match (true) {
            $e instanceof TransactionValidationException => $e->field,
            $e instanceof InvalidRateException => 'rate',
            $e instanceof InsufficientStockException,
            $e instanceof PositionLimitExceededException => 'amount_foreign',
            $e instanceof InvalidCurrencyException,
            $e instanceof CurrencyNotFoundException => 'currency_code',
            $e instanceof CustomerBlockedException,
            $e instanceof CustomerNotFoundException,
            $e instanceof KycExpiredException,
            $e instanceof PepApprovalRequiredException,
            $e instanceof PermissionDeniedException => 'customer_id',
            $e instanceof TillBalanceMissingException,
            $e instanceof TillClosedException,
            $e instanceof NoActiveCounterSessionException,
            $e instanceof AllocationValidationException,
            $e instanceof InvalidAllocationStateException,
            $e instanceof InsufficientAllocationBalanceException,
            $e instanceof PendingAllocationNotFoundException => 'counter_id',
            default => null,
        };
    }
}
