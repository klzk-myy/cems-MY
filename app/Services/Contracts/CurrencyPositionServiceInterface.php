<?php

namespace App\Services\Contracts;

use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface CurrencyPositionServiceInterface
{
    public function updatePosition(
        string $currencyCode,
        string $quantity,
        string $rate,
        string $type,
        ?string $branchId = null,
        ?Transaction $snapshotFor = null
    ): CurrencyPosition;

    public function getPositionWithLock(string $currencyCode, string $branchId): ?CurrencyPosition;

    public function getPosition(string $currencyCode, ?string $branchId = null): ?CurrencyPosition;

    public function getPositionForTransaction(string $currencyCode, string $branchId): ?CurrencyPosition;

    public function getAllPositions(?string $branchId = null): Collection;

    public function getTotalPnl(?string $branchId = null): string;

    public function getVisiblePositionsForUser(User $user): Collection;

    public function aggregateForUser(User $user): array;

    public function getAvailableBalance(string $currencyCode, string $branchId, ?string $tillId = null): string;

    public function reserveStock(Transaction $transaction): StockReservation;

    public function consumeStockReservation(int $transactionId): ?StockReservation;

    public function releaseStockReservation(int $transactionId): ?StockReservation;
}
