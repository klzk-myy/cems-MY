<?php

namespace App\Services\Contracts;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\PreValidationResult;

interface TransactionServiceInterface
{
    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult;

    public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction;

    public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array;

    public function isRefundable(Transaction $transaction): bool;

    public function isCancelled(Transaction $transaction): bool;
}
