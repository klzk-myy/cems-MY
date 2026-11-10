<?php

namespace App\Services\Transaction;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Support\Facades\Log;

class StockReleaseService
{
    public function __construct(
        protected CurrencyPositionService $positionService,
        protected TillBalanceManager $tillBalanceManager,
    ) {}

    public function releaseReservation(Transaction $transaction): void
    {
        $hasReservation = StockReservation::where('transaction_id', $transaction->id)
            ->where('status', StockReservationStatus::Pending->value)
            ->exists();

        if ($hasReservation) {
            $this->positionService->releaseStockReservation($transaction->id);
            Log::info('Stock reservation released for cancelled transaction', [
                'transaction_id' => $transaction->id,
            ]);
        }
    }
}
