<?php

namespace App\Services;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionService;
use Illuminate\Support\Facades\Log;

class StockReleaseService
{
    public function __construct(
        protected CurrencyPositionService $positionService,
    ) {}

    public function releaseReservation(Transaction $transaction): void
    {
        $hasReservation = StockReservation::where('transaction_id', $transaction->id)
            ->where('status', StockReservationStatus::Pending)
            ->exists();

        if ($hasReservation) {
            $this->positionService->releaseStockReservation($transaction->id);
            Log::info('Stock reservation released for cancelled transaction', [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    public function restorePositions(Transaction $transaction): void
    {
        $this->positionService->reversePositions($transaction);
    }

    public function releaseTillBalance(Transaction $transaction): void {}
}
