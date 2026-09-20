<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $currentPeriod = now()->format('Y-m');

        // UserSeeder does not guarantee id 1 — the admin row may sit at any
        // id depending on insert order, so resolve it rather than hardcoding.
        $createdBy = User::where('role', UserRole::Admin->value)->value('id')
            ?? User::min('id');

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
                    'budget_myr' => $amount,
                    'notes' => 'Monthly expense budget',
                    'created_by' => $createdBy,
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
                    'budget_myr' => $amount,
                    'notes' => 'Monthly revenue target',
                    'created_by' => $createdBy,
                ]
            );
        }

        $this->command->info('Created budgets for period: '.$currentPeriod);
    }
}
