<?php

namespace Database\Factories;

use App\Models\DataBreachAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataBreachAlertFactory extends Factory
{
    protected $model = DataBreachAlert::class;

    public function definition(): array
    {
        return [
            'alert_type' => $this->faker->randomElement(['unauthorized_access', 'suspicious_activity', 'data_export', 'failed_login']),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => $this->faker->sentence(),
            'record_count' => $this->faker->numberBetween(1, 100),
            'triggered_by' => User::factory(),
            'ip_address' => $this->faker->ipv4(),
            'is_resolved' => false,
        ];
    }
}
