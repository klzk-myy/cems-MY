<?php

namespace App\Services\Transaction;

use App\Enums\StockReservationStatus;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
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

    public function restorePositions(Transaction $transaction): void
    {
        $this->positionService->reversePositions($transaction);
    }

    public function releaseTillBalance(Transaction $transaction): void
    {
        $counter = Counter::findByCodeOrId($transaction->till_id);

        if (! $counter) {
            Log::warning('No counter found for till balance release', [
                'transaction_id' => $transaction->id,
                'till_id' => $transaction->till_id,
            ]);

            throw new TillBalanceMissingException($transaction->currency_code, (string) $transaction->till_id);
        }

        $tillBalance = $this->tillBalanceManager->currentBalance($counter, $transaction->currency_code, true);

        if (! $tillBalance) {
            Log::warning('No open till balance found for release', [
                'transaction_id' => $transaction->id,
                'till_id' => $transaction->till_id,
                'currency_code' => $transaction->currency_code,
            ]);

            throw new TillBalanceMissingException($transaction->currency_code, (string) $transaction->till_id);
        }

        $this->tillBalanceManager->reverseTransaction(
            $tillBalance,
            $transaction->type,
            (string) $transaction->amount_myr,
            (string) $transaction->quantity
        );

        Log::info('Till balance released for cancelled transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
        ]);
    }
}
