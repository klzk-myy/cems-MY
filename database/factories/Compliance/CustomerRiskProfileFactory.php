<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRiskProfile>
 */
class CustomerRiskProfileFactory extends Factory
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
            'risk_score' => $this->faker->numberBetween(0, 100),
            'risk_tier' => $this->faker->randomElement(['Low', 'Medium', 'High', 'Critical']),
            'risk_factors' => [
                'pep_status' => $this->faker->boolean,
                'sanctions_match' => $this->faker->boolean,
                'suspicious_activity' => $this->faker->boolean,
            ],
            'previous_score' => $this->faker->optional()->numberBetween(0, 100),
            'score_changed_at' => $this->faker->dateTimeThisMonth(),
            'next_scheduled_recalculation' => $this->faker->dateTimeThisYear(),
            'recalculation_trigger' => $this->faker->optional()->randomElement(['Manual', 'Scheduled', 'Event_Driven']),
            'locked_until' => $this->faker->optional()->dateTimeThisYear(),
            'locked_by' => User::factory(),
            'lock_reason' => $this->faker->optional()->sentence(),
        ];
    }
}
