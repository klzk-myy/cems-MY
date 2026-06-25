<?php

namespace Database\Factories;

use App\Models\BranchPool;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchPoolFactory extends Factory
{
    protected $model = BranchPool::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'currency_code' => 'USD',
            'available_balance' => '0.0000',
            'allocated_balance' => '0.0000',
        ];
    }

    public function myr(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => 'MYR',
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => 'USD',
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_balance' => '0.0000',
            'allocated_balance' => '0.0000',
        ]);
    }
}
