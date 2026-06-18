<?php

namespace App\Services\Contracts;

use App\Models\Customer;
use App\Models\TillBalance;

interface TransactionValidationInterface
{
    public function validateCurrency(string $currencyCode): void;

    public function validateTillBalance(string $tillId, string $currencyCode): TillBalance;

    public function validateIpAddress(?string $ipAddress): void;

    public function validatePepRequirements(Customer $customer, array $data): void;
}
