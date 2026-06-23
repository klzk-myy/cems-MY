<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'account_code' => ChartOfAccount::factory(),
            'period_code' => now()->format('Y-m'),
            'budget_amount' => '5000.00',
            'actual_amount' => '0.00',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
