<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class BranchPoolSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $currencies = Currency::where('code', '!=', 'MYR')->where('is_active', true)->get();

        $initialBalances = [
            'USD' => '50000.0000',
            'EUR' => '40000.0000',
            'GBP' => '30000.0000',
            'SGD' => '35000.0000',
            'AUD' => '25000.0000',
            'JPY' => '2000000.0000',
            'CHF' => '20000.0000',
            'CAD' => '25000.0000',
            'HKD' => '80000.0000',
            'CNY' => '100000.0000',
        ];

        $myrFloat = '200000.0000';

        foreach ($branches as $branch) {
            // MYR till float: buys pay out in ringgit, so each branch needs a
            // pool + position row for the base currency too.
            BranchPool::updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => 'MYR'],
                ['available_balance' => $myrFloat, 'allocated_balance' => '0.0000']
            );
            CurrencyPosition::updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => 'MYR'],
                [
                    'quantity' => $myrFloat,
                    'average_cost' => '1',
                    'current_rate' => '1',
                    'total_cost' => $myrFloat,
                    'current_value' => $myrFloat,
                ]
            );

            foreach ($currencies as $currency) {
                $initialAmount = $initialBalances[$currency->code] ?? '10000.0000';

                BranchPool::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'currency_code' => $currency->code,
                    ],
                    [
                        'available_balance' => $initialAmount,
                        'allocated_balance' => '0.0000',
                    ]
                );

                // currency_positions is the authoritative stock gate — a
                // branch pool without a matching position row cannot sell.
                // Cost basis = seeded buy rate.
                $buyRate = (string) (ExchangeRate::where('currency_code', $currency->code)
                    ->latest()->value('rate_buy') ?? '1');

                CurrencyPosition::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'currency_code' => $currency->code,
                    ],
                    [
                        'quantity' => $initialAmount,
                        'average_cost' => $buyRate,
                        'current_rate' => $buyRate,
                        'total_cost' => bcmul($initialAmount, $buyRate, 4),
                        'current_value' => bcmul($initialAmount, $buyRate, 4),
                    ]
                );

                $this->command->info("Seeded branch pool for {$branch->code} - {$currency->code}: {$initialAmount}");
            }
        }

        $this->command->info('Branch pool seeding completed');
    }
}
