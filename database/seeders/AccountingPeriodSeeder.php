<?php

namespace Database\Seeders;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalYearStatus;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $openYears = FiscalYear::where('status', FiscalYearStatus::Open->value)->get();

        // Attach each period to the open fiscal year covering its start date,
        // if one exists — keeps the year view's period list and the
        // all-periods-closed year-close guard accurate.
        $fiscalYearIdFor = fn (string $startDate) => $openYears
            ->first(fn (FiscalYear $y) => $startDate >= $y->start_date->toDateString()
                && $startDate <= $y->end_date->toDateString())?->id;

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
                'status' => AccountingPeriodStatus::Open->value,
                'fiscal_year_id' => $fiscalYearIdFor($currentStart->toDateString()),
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
                'status' => AccountingPeriodStatus::Open->value,
                'fiscal_year_id' => $fiscalYearIdFor($prevStart->toDateString()),
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
                'status' => AccountingPeriodStatus::Open->value,
                'fiscal_year_id' => $fiscalYearIdFor($nextStart->toDateString()),
            ]
        );

        $this->command?->info('Created accounting periods: '.$currentPeriodCode.', '.$prevPeriodCode.', '.$nextPeriodCode);
    }
}
