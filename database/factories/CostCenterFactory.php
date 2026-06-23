<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $codeCounter = 0;

        return [
            'code' => 'CC-'.str_pad(++$codeCounter, 4, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(90),
            'department_id' => Department::factory(),
        ];
    }
}
