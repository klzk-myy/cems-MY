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
        string $amount,
        string $rate,
        string $type,
        string $tillId = 'MAIN'
    ): CurrencyPosition;

    public function getPositionWithLock(string $currencyCode, string $tillId): ?CurrencyPosition;

    public function getPosition(string $currencyCode, ?string $tillId = null): ?CurrencyPosition;

    public function getPositionForTransaction(string $currencyCode, string $tillId): ?CurrencyPosition;

    public function getAllPositions(string $tillId = 'MAIN'): Collection;

    public function getTotalPnl(string $tillId = 'MAIN'): string;

    public function getVisiblePositionsForUser(User $user): Collection;

    public function aggregateForUser(User $user): array;

    public function getAvailableBalance(string $currencyCode, string $tillId): string;

    public function reserveStock(Transaction $transaction): StockReservation;

    public function consumeStockReservation(int $transactionId): ?StockReservation;

    public function releaseStockReservation(int $transactionId): ?StockReservation;
}
