<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $transferNumberCounter = 0;

        return [
            'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.str_pad(++$transferNumberCounter, 4, '0', STR_PAD_LEFT),
            'type' => $this->faker->randomElement(['Standard', 'Emergency', 'Scheduled', 'Return']),
            'status' => $this->faker->randomElement(['Requested', 'BranchManagerApproved', 'HQApproved', 'InTransit', 'PartiallyReceived', 'Completed', 'Cancelled', 'Rejected']),
            'source_branch_name' => $this->faker->city().' Branch',
            'destination_branch_name' => $this->faker->city().' Branch',
            'requested_by' => User::factory(),
            'requested_at' => $this->faker->dateTimeThisMonth(),
            'branch_manager_approved_by' => User::factory(),
            'branch_manager_approved_at' => $this->faker->optional()->dateTime(),
            'hq_approved_by' => User::factory(),
            'hq_approved_at' => $this->faker->optional()->dateTime(),
            'dispatched_at' => $this->faker->optional()->dateTime(),
            'completed_at' => $this->faker->optional()->dateTime(),
            'notes' => $this->faker->optional()->sentence(),
            'cancellation_reason' => $this->faker->optional()->sentence(),
            'total_value_myr' => $this->faker->randomFloat(2, 1000, 100000),
        ];
    }
}
