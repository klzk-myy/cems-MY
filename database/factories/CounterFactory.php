<?php

namespace Database\Factories;

use App\Models\Counter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Counter>
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
        ];
    }
}
