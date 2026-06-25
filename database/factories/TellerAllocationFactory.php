<?php

namespace Database\Factories;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TellerAllocationFactory extends Factory
{
    protected $model = TellerAllocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'counter_id' => Counter::factory(),
            'currency_code' => 'USD',
            'allocated_amount' => $this->faker->randomFloat(4, 10000, 100000),
            'current_balance' => $this->faker->randomFloat(4, 10000, 100000),
            'requested_amount' => $this->faker->randomFloat(4, 10000, 100000),
            'daily_limit_myr' => $this->faker->randomFloat(4, 50000, 500000),
            'daily_used_myr' => $this->faker->randomFloat(4, 0, 50000),
            'status' => TellerAllocationStatus::PENDING,
            'session_date' => now()->toDateString(),
            'approved_by' => null,
            'approved_at' => null,
            'opened_at' => null,
            'closed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TellerAllocationStatus::PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TellerAllocationStatus::APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TellerAllocationStatus::ACTIVE,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDay(),
            'opened_at' => now(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TellerAllocationStatus::RETURNED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDay(),
            'opened_at' => now()->subHours(4),
            'closed_at' => now(),
        ]);
    }
}
