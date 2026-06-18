<?php

namespace App\Services\Contracts;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\Transaction;

interface ComplianceServiceInterface
{
    public function determineCDDLevel(string $amount, Customer $customer): CddLevel;

    public function getLastCddTriggers(): array;

    public function checkSanctionMatch(Customer $customer): bool;

    public function checkVelocity(int $customerId, string $newAmount): array;

    public function checkStructuring(int $customerId): bool;

    public function requiresHold(string $amount, Customer $customer): array;

    public function checkAggregateTransactions(int $customerId, string $currentAmount): array;

    public function checkTransactionDuration(Transaction $transaction): array;

    public function getCustomerOpenFlags(int $customerId): array;

    public function verifyCddDocuments(Customer $customer, CddLevel $cddLevel): array;
}
