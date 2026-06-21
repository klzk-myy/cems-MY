<?php

namespace Database\Factories;

use App\Models\ThresholdAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThresholdAudit>
 */
class ThresholdAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['transaction', 'customer', 'compliance', 'system']),
            'key' => 'threshold_'.$this->faker->slug,
            'old_value' => $this->faker->randomFloat(2, 100, 10000),
            'new_value' => $this->faker->randomFloat(2, 100, 10000),
            'changed_by' => User::factory(),
            'change_reason' => $this->faker->sentence(),
            'changed_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
