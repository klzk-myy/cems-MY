<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'code' => 'DEPT-'.str_pad(++$codeCounter, 3, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
