<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class BranchPoolSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $currencies = Currency::where('code', '!=', 'MYR')->where('is_active', true)->get();

        $initialBalances = [
            'USD' => 50000.00,
            'EUR' => 40000.00,
            'GBP' => 30000.00,
            'SGD' => 35000.00,
            'AUD' => 25000.00,
            'JPY' => 2000000.00,
            'CHF' => 20000.00,
            'CAD' => 25000.00,
            'HKD' => 80000.00,
            'CNY' => 100000.00,
        ];

        foreach ($branches as $branch) {
            foreach ($currencies as $currency) {
                $initialAmount = $initialBalances[$currency->code] ?? 10000.00;

                BranchPool::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'currency_code' => $currency->code,
                    ],
                    [
                        'available_balance' => (string) $initialAmount,
                        'allocated_balance' => '0.00',
                    ]
                );

                $this->command->info("Seeded branch pool for {$branch->code} - {$currency->code}: {$initialAmount}");
            }
        }

        $this->command->info('Branch pool seeding completed');
    }
}
