<?php

namespace Database\Factories;

use App\Models\CurrencyPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrencyPosition>
 */
class CurrencyPositionFactory extends Factory
{
    protected $model = CurrencyPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_code' => 'USD',
            'branch_id' => '1',
            'quantity' => $this->faker->randomNumber(5) * 1000,
            'average_cost' => '4.5000',
            'total_cost' => '0.0000',
            'current_rate' => '4.5000',
            'current_value' => '0.0000',
            'unrealized_gain_loss' => '0.0000',
            'last_revalued_at' => now(),
        ];
    }
}
