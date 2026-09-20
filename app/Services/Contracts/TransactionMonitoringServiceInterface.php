<?php

namespace App\Services\Contracts;

use App\Enums\TransactionStatus;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;

interface TransactionMonitoringServiceInterface
{
    /**
     * @return array{transaction_id: int, flags_created: int, flags: array<int, FlaggedTransaction>, status: TransactionStatus}
     */
    public function monitorTransaction(Transaction $transaction): array;

    public function getOpenFlags(): array;

    public function assignFlag(int $flagId, int $userId): bool;

    public function resolveFlag(int $flagId, int $userId, ?string $notes = null): bool;
}
