<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRateHistory>
 */
class ExchangeRateHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'currency_code' => Currency::factory(),
            'rate' => $this->faker->randomFloat(6, 0.1, 10),
            'effective_date' => $this->faker->date(),
            'created_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
