<?php

namespace Database\Factories;

use App\Models\AmlRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmlRule>
 */
class AmlRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_code' => 'RULE-'.strtoupper($this->faker->unique()->bothify('???##')),
            'rule_name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'rule_type' => $this->faker->randomElement(['velocity', 'structuring', 'amount_threshold', 'frequency', 'geographic']),
            'conditions' => [
                'window_hours' => $this->faker->numberBetween(1, 24),
                'max_transactions' => $this->faker->numberBetween(5, 50),
                'cumulative_threshold' => $this->faker->optional()->randomFloat(2, 1000, 100000),
                'min_amount' => $this->faker->randomFloat(2, 500, 50000),
            ],
            'action' => $this->faker->randomElement(['flag', 'hold', 'block']),
            'risk_score' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80),
            'created_by' => User::factory(),
        ];
    }
}
