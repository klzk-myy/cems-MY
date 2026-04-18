<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use Illuminate\Database\Seeder;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Create current month period if not exists
        $currentPeriodCode = $now->format('Y-m');
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();

        AccountingPeriod::firstOrCreate(
            ['period_code' => $currentPeriodCode],
            [
                'start_date' => $currentStart->toDateString(),
                'end_date' => $currentEnd->toDateString(),
                'period_type' => 'month',
                'status' => 'open',
            ]
        );

        // Create previous month period
        $prevPeriodCode = $now->copy()->subMonth()->format('Y-m');
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd = $now->copy()->subMonth()->endOfMonth();

        AccountingPeriod::firstOrCreate(
            ['period_code' => $prevPeriodCode],
            [
                'start_date' => $prevStart->toDateString(),
                'end_date' => $prevEnd->toDateString(),
                'period_type' => 'month',
                'status' => 'open',
            ]
        );

        // Create next month period (for planning)
        $nextPeriodCode = $now->copy()->addMonth()->format('Y-m');
        $nextStart = $now->copy()->addMonth()->startOfMonth();
        $nextEnd = $now->copy()->addMonth()->endOfMonth();

        AccountingPeriod::firstOrCreate(
            ['period_code' => $nextPeriodCode],
            [
                'start_date' => $nextStart->toDateString(),
                'end_date' => $nextEnd->toDateString(),
                'period_type' => 'month',
                'status' => 'open',
            ]
        );

        $this->command->info('Created accounting periods: '.$currentPeriodCode.', '.$prevPeriodCode.', '.$nextPeriodCode);
    }
}
