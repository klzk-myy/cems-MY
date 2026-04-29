<?php

namespace App\Console\Commands;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\ReservationExpiredNotification;
use App\Services\CurrencyPositionService;
use Illuminate\Console\Command;

class ExpireStockReservations extends Command
{
    protected $signature = 'reservation:expire';

    protected $description = 'Release expired stock reservations';

    public function __construct(
        protected CurrencyPositionService $positionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = StockReservation::where('status', StockReservationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->get();

        $count = $expired->count();

        foreach ($expired as $reservation) {
            $this->positionService->releaseStockReservation($reservation->transaction_id);
            $this->notifyTeller($reservation);
        }

        $this->info("Released {$count} expired stock reservations.");

        return Command::SUCCESS;
    }

    protected function notifyTeller(StockReservation $reservation): void
    {
        $transaction = $reservation->transaction;
        if ($transaction) {
            $teller = User::find($transaction->user_id);
            if ($teller) {
                $teller->notify(new ReservationExpiredNotification($reservation));
            }
        }
    }
}
