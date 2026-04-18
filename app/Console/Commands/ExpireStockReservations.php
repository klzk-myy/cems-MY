<?php

namespace App\Console\Commands;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use Illuminate\Console\Command;

class ExpireStockReservations extends Command
{
    protected $signature = 'reservation:expire';

    protected $description = 'Release expired stock reservations';

    public function handle(): int
    {
        $expired = StockReservation::where('status', StockReservationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->get();

        $count = $expired->count();

        foreach ($expired as $reservation) {
            $reservation->update(['status' => StockReservationStatus::Released]);
        }

        $this->info("Released {$count} expired stock reservations.");

        return Command::SUCCESS;
    }
}
