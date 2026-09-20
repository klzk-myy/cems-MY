<?php

namespace App\Console\Commands;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\ReservationExpiredNotification;
use App\Services\Accounting\CurrencyPositionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        // Filter expiry in the query and chunk so large backlogs don't blow memory.
        $released = 0;
        $failed = 0;

        StockReservation::where('status', StockReservationStatus::Pending)
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($reservations) use (&$released, &$failed) {
                foreach ($reservations as $reservation) {
                    try {
                        // A reservation expiring on an already-terminal
                        // transaction means a reject/cancel path leaked it —
                        // surface that instead of silently sweeping it.
                        $finalStatus = $reservation->transaction?->status;
                        if ($finalStatus !== null && $finalStatus->isFinal()) {
                            Log::warning('Expired reservation belonged to a final-state transaction — check for a reservation leak on that exit path', [
                                'reservation_id' => $reservation->id,
                                'transaction_id' => $reservation->transaction_id,
                                'transaction_status' => $finalStatus->value,
                            ]);
                        }

                        $this->positionService->releaseStockReservation($reservation->transaction_id);
                        $this->notifyTeller($reservation);
                        $released++;
                    } catch (\Throwable $e) {
                        // One failing release must not abort the remaining ones.
                        $failed++;

                        Log::error('Failed to release expired stock reservation', [
                            'reservation_id' => $reservation->id,
                            'transaction_id' => $reservation->transaction_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Released {$released} expired stock reservations.");

        if ($failed > 0) {
            $this->warn("{$failed} expired stock reservations failed to release (see log).");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function notifyTeller(StockReservation $reservation): void
    {
        $transaction = $reservation->transaction;
        if ($transaction) {
            /** @var User|null $teller */
            $teller = User::find($transaction->user_id);
            if ($teller) {
                $teller->notify(new ReservationExpiredNotification($reservation));
            }
        }
    }
}
