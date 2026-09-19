<?php

namespace Database\Factories;

use App\Enums\AccountCode;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'account_code' => AccountCode::OPERATING_EXPENSES->value,
            'category' => 'operating',
            'description' => $this->faker->sentence(),
            'amount_myr' => $this->faker->randomFloat(2, 10, 500),
            'expense_date' => $this->faker->date(),
            'journal_entry_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
