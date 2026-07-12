<?php

namespace App\Services\Contracts;

use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Transaction;
use App\Services\Transaction\DTOs\TransactionCreationContext;

interface TransactionCreationServiceInterface
{
    /**
     * Create a new transaction with all side effects.
     *
     * @param  TransactionCreationContext  $context  Pre-validated context with all data needed
     * @param  int|null  $userId  User ID of transaction creator (null uses auth()->id())
     * @param  string|null  $ipAddress  IP address for audit (null uses request()->ip())
     * @return Transaction The created transaction (with relationships loaded as needed)
     *
     * @throws DuplicateTransactionException If recent duplicate detected
     * @throws InsufficientStockException If insufficient stock for Sell transaction
     * @throws TillBalanceMissingException If MYR till balance missing
     * @throws StockReservationExpiredException If stock reservation not found when needed
     * @throws \InvalidArgumentException If till is closed or other validation fails
     */
    public function create(TransactionCreationContext $context, ?int $userId = null, ?string $ipAddress = null): Transaction;

    /**
     * Validate raw transaction data, build a creation context, and create the transaction.
     *
     * This is the controller-facing entry point that replaces the orchestration
     * previously done in TransactionService::prepareAndCreate().
     *
     * @param  array  $data  Validated transaction payload
     * @param  int|null  $userId  User ID of transaction creator (null uses auth()->id())
     * @param  string|null  $ipAddress  IP address for audit (null uses request()->ip())
     * @return Transaction The created transaction
     *
     * @throws \InvalidArgumentException
     * @throws TransactionBlockedException
     * @throws AllocationValidationException
     */
    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction;
}
