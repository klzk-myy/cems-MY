<?php

namespace Database\Factories;

use App\Enums\SystemAlertLevel;
use App\Models\SystemAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemAlertFactory extends Factory
{
    protected $model = SystemAlert::class;

    public function definition(): array
    {
        return [
            'level' => $this->faker->randomElement([
                SystemAlertLevel::Info->value,
                SystemAlertLevel::Warning->value,
                SystemAlertLevel::Critical->value,
            ]),
            'message' => $this->faker->sentence(),
            'source' => $this->faker->randomElement(['system_monitor', 'database', 'queue', 'security']),
            'metadata' => null,
        ];
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemAlertLevel::Warning,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemAlertLevel::Critical,
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
        ]);
    }
}
