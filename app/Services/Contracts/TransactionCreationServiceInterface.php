<?php

namespace App\Services\Contracts;

use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DTOs\PreValidationResult;
use App\Services\Transaction\DTOs\TransactionCreationContext;

interface TransactionCreationServiceInterface
{
    /**
     * Shared booking gate — the single eligibility check every creation path
     * (web form, wizard, API, CSV import) must pass before a transaction
     * record exists.
     *
     * @param  array<string, mixed>  $data  Row/request payload (unit-quoted rate).
     * @return array{0: ?TillBalance, 1: Customer} locked till row (null when booking drawer-less) and validated customer
     */
    public function assertBookingEligibility(User $user, array $data, ?string $ipAddress = null): array;

    /**
     * PEP requirements + sanctions/CDD/risk/hold pre-validation — run after
     * the amount is converted to MYR.
     *
     * @param  array<string, mixed>  $data
     */
    public function runComplianceGates(Customer $customer, array $data, string $amountMyr): PreValidationResult;

    /**
     * Create a new transaction with all side effects.
     *
     * @param  TransactionCreationContext  $context  Pre-validated context with all data needed
     * @param  int|null  $userId  User ID of transaction creator (null resolves via ActorContext)
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
     * @param  int|null  $userId  User ID of transaction creator (null resolves via ActorContext)
     * @param  string|null  $ipAddress  IP address for audit (null uses request()->ip())
     * @return Transaction The created transaction
     *
     * @throws \InvalidArgumentException
     * @throws TransactionBlockedException
     * @throws AllocationValidationException
     */
    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction;
}
