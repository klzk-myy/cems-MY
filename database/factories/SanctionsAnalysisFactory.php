<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SanctionsAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SanctionsAnalysis>
 */
class SanctionsAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'analysis_type' => $this->faker->randomElement(['sanction', 'pep', 'risk', 'related_party_due_diligence']),
            'transaction_count' => $this->faker->numberBetween(0, 1000),
            'total_amount' => $this->faker->randomFloat(2, 1000, 1000000),
            'analyzed_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
