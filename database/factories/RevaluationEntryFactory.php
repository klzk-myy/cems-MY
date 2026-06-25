<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\RevaluationEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevaluationEntry>
 */
class RevaluationEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_code' => Currency::factory(),
            'till_id' => $this->faker->numberBetween(1, 100),
            'old_rate' => $this->faker->randomFloat(6, 0.1, 10),
            'new_rate' => $this->faker->randomFloat(6, 0.1, 10),
            'position_amount' => $this->faker->randomFloat(2, 1000, 100000),
            'gain_loss_amount' => $this->faker->randomFloat(2, -1000, 1000),
            'revaluation_date' => $this->faker->date(),
            'posted_by' => User::factory(),
        ];
    }
}
