<?php

namespace Database\Seeders;

use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $currentPeriod = now()->format('Y-m');

        // Budget for Expense accounts (sample monthly budgets)
        $budgets = [
            '6000' => 50000.00,  // Expense - Forex Loss
            '6100' => 10000.00,  // Expense - Revaluation Loss
            '6200' => 30000.00,  // Expense - Operating
        ];

        foreach ($budgets as $accountCode => $amount) {
            Budget::firstOrCreate(
                [
                    'account_code' => $accountCode,
                    'period_code' => $currentPeriod,
                ],
                [
                    'budget_amount' => $amount,
                    'notes' => 'Monthly expense budget',
                    'created_by' => 1,
                ]
            );
        }

        // Budget for Revenue accounts (expected monthly revenue targets)
        $revenueBudgets = [
            '5000' => 100000.00,  // Revenue - Forex Trading
            '5100' => 5000.00,    // Revenue - Revaluation Gain
        ];

        foreach ($revenueBudgets as $accountCode => $amount) {
            Budget::firstOrCreate(
                [
                    'account_code' => $accountCode,
                    'period_code' => $currentPeriod,
                ],
                [
                    'budget_amount' => $amount,
                    'notes' => 'Monthly revenue target',
                    'created_by' => 1,
                ]
            );
        }

        $this->command->info('Created budgets for period: '.$currentPeriod);
    }
}
