<?php

namespace Database\Factories;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Models\Alert;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => $this->faker->randomElement(ComplianceFlagType::cases()),
            'priority' => $this->faker->randomElement(AlertPriority::cases()),
            'risk_score' => $this->faker->numberBetween(0, 100),
            'reason' => $this->faker->sentence(),
            'source' => $this->faker->randomElement(['automated', 'manual']),
            'status' => 'Open',
        ];
    }

    public function structuring(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ComplianceFlagType::Structuring,
            'priority' => AlertPriority::High,
            'reason' => 'Sub-RM3k transactions detected within 1 hour',
        ]);
    }

    public function sanctionMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ComplianceFlagType::SanctionMatch,
            'priority' => AlertPriority::Critical,
            'reason' => 'Customer matches sanctions list',
        ]);
    }

    public function velocity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ComplianceFlagType::Velocity,
            'priority' => AlertPriority::High,
            'reason' => '24-hour velocity threshold exceeded',
        ]);
    }
}
