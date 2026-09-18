<?php

namespace Database\Seeders;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;

class TellerAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $tellers = User::where('role', 'teller')->get();

        // Skip if no tellers exist (e.g., during initial setup)
        if ($tellers->isEmpty()) {
            $this->command->info('No tellers found. Skipping teller allocation.');

            return;
        }

        $branches = Branch::all();
        $currencies = Currency::where('code', '!=', 'MYR')->where('is_active', true)->get();

        $allocationAmounts = [
            'USD' => 10000.00,
            'EUR' => 8000.00,
            'GBP' => 6000.00,
            'SGD' => 7000.00,
            'AUD' => 5000.00,
            'JPY' => 400000.00,
            'CHF' => 4000.00,
            'CAD' => 5000.00,
            'HKD' => 16000.00,
            'CNY' => 20000.00,
        ];

        foreach ($tellers as $teller) {
            $branch = $branches->random();

            foreach ($currencies as $currency) {
                $amount = $allocationAmounts[$currency->code] ?? 5000.00;

                TellerAllocation::updateOrCreate(
                    [
                        'user_id' => $teller->id,
                        'branch_id' => $branch->id,
                        'currency_code' => $currency->code,
                        'session_date' => today(),
                    ],
                    [
                        'requested_amount' => (string) $amount,
                        'allocated_amount' => (string) $amount,
                        'current_balance' => (string) $amount,
                        'status' => TellerAllocationStatus::Active,
                        'approved_by' => User::where('role', 'manager')->first()?->id,
                        'approved_at' => now(),
                    ]
                );

                $this->command->info("Allocated {$amount} {$currency->code} to teller {$teller->username}");
            }
        }

        $this->command->info('Teller allocation seeding completed');
    }
}
