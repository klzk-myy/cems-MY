<?php

namespace Database\Factories;

use App\Models\SystemAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemAlertFactory extends Factory
{
    protected $model = SystemAlert::class;

    public function definition(): array
    {
        return [
            'level' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'message' => $this->faker->sentence(),
            'source' => $this->faker->randomElement(['system_monitor', 'database', 'queue', 'security']),
            'metadata' => null,
        ];
    }
}
