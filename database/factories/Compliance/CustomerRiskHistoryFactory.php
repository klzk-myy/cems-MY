<?php

namespace Database\Factories\Compliance;

use App\Enums\RiskRating;
use App\Models\Compliance\CustomerRiskHistory;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRiskHistory>
 */
class CustomerRiskHistoryFactory extends Factory
{
    protected $model = CustomerRiskHistory::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'old_score' => $this->faker->numberBetween(0, 100),
            'new_score' => $this->faker->numberBetween(0, 100),
            'old_rating' => RiskRating::Medium->value,
            'new_rating' => RiskRating::High->value,
            'change_reason' => $this->faker->sentence(),
            'assessed_by' => User::factory(),
        ];
    }
}
