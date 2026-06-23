<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PepApprovalRequest>
 */
class PepApprovalRequestFactory extends Factory
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
            'transaction_type' => $this->faker->randomElement(['account_opening', 'large_transaction', 'wire_transfer']),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'approval_level' => $this->faker->randomElement(['level1', 'level2', 'level3', 'head_office_senior_management']),
            'requested_at' => $this->faker->dateTimeThisMonth(),
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->optional()->dateTime(),
            'rejected_by' => User::factory(),
            'rejected_at' => $this->faker->optional()->dateTime(),
            'rejection_reason' => $this->faker->optional()->sentence(),
        ];
    }
}
