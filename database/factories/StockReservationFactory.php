<?php

namespace Database\Factories;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'currency_code' => 'USD',
            'till_id' => 'MAIN',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => User::factory(),
        ];
    }
}
