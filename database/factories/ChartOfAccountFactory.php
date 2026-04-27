<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'account_code' => fake()->unique()->numerify('####'),
            'account_name' => fake()->words(3, true),
            'account_type' => fake()->randomElement(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Off-Balance']),
            'account_class' => fake()->word(),
            'parent_code' => null,
            'is_active' => true,
            'allow_journal' => true,
            'cost_center_id' => null,
            'department_id' => null,
        ];
    }
}
