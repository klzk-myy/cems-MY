<?php

namespace Database\Factories;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemLog>
 */
class SystemLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => $this->faker->word,
            'description' => $this->faker->sentence(),
            'severity' => $this->faker->randomElement(['INFO', 'WARNING', 'ERROR', 'CRITICAL']),
            'entity_type' => $this->faker->randomElement(['App\Models\User', 'App\Models\Transaction', 'App\Models\Customer']),
            'entity_id' => $this->faker->numberBetween(1, 1000),
            'old_values' => $this->faker->optional() ? ['status' => 'archived'] : null,
            'new_values' => $this->faker->optional() ? ['status' => 'active'] : null,
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'session_id' => $this->faker->md5,
            'previous_hash' => $this->faker->sha1,
            'entry_hash' => $this->faker->sha1,
            'created_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
