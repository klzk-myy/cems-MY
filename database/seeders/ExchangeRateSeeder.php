<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $baseCurrency = 'MYR';

        $rates = [
            'USD' => ['buy' => 4.7200, 'sell' => 4.8100],
            'EUR' => ['buy' => 5.0800, 'sell' => 5.1800],
            'GBP' => ['buy' => 6.0200, 'sell' => 6.1300],
            'SGD' => ['buy' => 3.5200, 'sell' => 3.5800],
            'AUD' => ['buy' => 3.1200, 'sell' => 3.1800],
            'JPY' => ['buy' => 0.0315, 'sell' => 0.0321],
            'CHF' => ['buy' => 5.3500, 'sell' => 5.4500],
            'CAD' => ['buy' => 3.4500, 'sell' => 3.5100],
            'HKD' => ['buy' => 0.6050, 'sell' => 0.6150],
            'CNY' => ['buy' => 0.6550, 'sell' => 0.6650],
        ];

        $now = Carbon::now();

        foreach ($rates as $currencyCode => $rate) {
            $currency = Currency::where('code', $currencyCode)->first();

            if (! $currency) {
                $this->command->warn("Currency {$currencyCode} not found, skipping rate seeding");

                continue;
            }

            ExchangeRate::updateOrCreate(
                [
                    'currency_code' => $currencyCode,
                ],
                [
                    'rate_buy' => $rate['buy'],
                    'rate_sell' => $rate['sell'],
                    'source' => 'initial_seed',
                    'fetched_at' => $now,
                ]
            );

            $this->command->info("Seeded exchange rate for {$currencyCode}: Buy {$rate['buy']}, Sell {$rate['sell']}");
        }

        $this->command->info('Exchange rate seeding completed');
    }
}
