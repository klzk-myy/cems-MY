<?php

namespace App\Services\Contracts;

use App\Models\Transaction;

interface TransactionMonitoringServiceInterface
{
    public function monitorTransaction(Transaction $transaction): array;

    public function getOpenFlags(): array;

    public function assignFlag(int $flagId, int $userId): bool;

    public function resolveFlag(int $flagId, int $userId, ?string $notes = null): bool;
}
