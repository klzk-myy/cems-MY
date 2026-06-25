<?php

namespace Database\Factories;

use App\Models\Counter;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TillBalanceFactory extends Factory
{
    protected $model = TillBalance::class;

    public function definition(): array
    {
        return [
            'till_id' => Counter::factory(),
            'currency_code' => 'USD',
            'opening_balance' => '10000.00',
            'closing_balance' => null,
            'variance' => null,
            'date' => today(),
            'opened_by' => User::factory(),
            'closed_by' => null,
            'closed_at' => null,
            'notes' => null,
            'foreign_total' => '0',
            'transaction_total' => '0',
        ];
    }
}
