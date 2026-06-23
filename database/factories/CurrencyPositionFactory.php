<?php

namespace Database\Factories;

use App\Models\CurrencyPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyPositionFactory extends Factory
{
    protected $model = CurrencyPosition::class;

    public function definition(): array
    {
        return [
            'currency_code' => 'USD',
            'branch_id' => 1,
            'till_id' => 'MAIN',
            'balance' => $this->faker->randomNumber(5) * 1000,
            'avg_cost_rate' => '4.5000',
            'last_valuation_rate' => '4.5000',
            'unrealized_pnl' => '0.0000',
            'last_valuation_at' => now(),
        ];
    }
}
