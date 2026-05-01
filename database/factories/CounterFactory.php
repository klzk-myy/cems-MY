<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counter>
 */
class CounterFactory extends Factory
{
    protected $model = Counter::class;

    public function definition(): array
    {
        return [
            'code' => 'C'.fake()->unique()->numberBetween(10, 99),
            'name' => 'Counter '.fake()->word(),
            'status' => 'active',
            'branch_id' => Branch::factory(),
        ];
    }
}
