<?php

namespace App\Services\Contracts;

use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\DTOs\PreValidationResult;

interface TransactionValidationInterface
{
    public function validateCurrency(string $currencyCode): void;

    /**
     * @param  User|null  $user  Explicit actor for queue/CLI contexts where no
     *                           authenticated user exists; defaults to ActorContext.
     */
    public function validateTillBalance(string $tillId, string $currencyCode, ?User $user = null): TillBalance;

    public function validateIpAddress(?string $ipAddress): void;

    public function validatePepRequirements(Customer $customer, array $data): void;

    /**
     * Run complete pre-transaction validation before creation.
     *
     * Consolidates:
     * - Sanctions screening (blocking)
     * - CDD level determination
     * - Historical risk analysis (for returning customers)
     * - Hold status determination
     *
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
     */
    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult;
}
