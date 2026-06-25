<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBehavioralBaseline>
 */
class CustomerBehavioralBaselineFactory extends Factory
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
            'currency_codes' => $this->faker->randomElements(['MYR', 'USD', 'EUR', 'SGD'], 2),
            'avg_transaction_size_myr' => $this->faker->randomFloat(2, 100, 10000),
            'avg_transaction_frequency' => $this->faker->randomFloat(2, 0.5, 50),
            'preferred_counter_ids' => null,
            'registered_location' => $this->faker->city(),
            'last_calculated_at' => $this->faker->dateTimeThisMonth(),
            'baseline_version' => 1,
        ];
    }
}
