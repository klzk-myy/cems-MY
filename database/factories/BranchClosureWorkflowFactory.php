<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchClosureWorkflow>
 */
class BranchClosureWorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'initiated_by' => User::factory(),
            'status' => $this->faker->randomElement(['initiated', 'settled', 'finalized']),
            'checklist' => [
                'cash_balanced' => $this->faker->boolean,
                'vault_sealed' => $this->faker->boolean,
                'keys_returned' => $this->faker->boolean,
            ],
            'settlement_at' => $this->faker->optional()->dateTime(),
            'finalized_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
