<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskScoreSnapshotFactory extends Factory
{
    protected $model = RiskScoreSnapshot::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'snapshot_date' => today(),
            'overall_score' => fake()->numberBetween(0, 100),
            'overall_rating_label' => fake()->randomElement(['Low', 'Medium', 'High']),
            'next_screening_date' => now()->addDays(30),
            'velocity_score' => 0,
            'structuring_score' => 0,
            'geographic_score' => 0,
            'amount_score' => 0,
            'trend' => 'stable',
            'factors' => [],
        ];
    }
}
