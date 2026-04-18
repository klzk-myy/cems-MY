<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = now()->year;
        $fiscalYear = FiscalYear::firstOrCreate(
            ['year_code' => 'FY'.$currentYear],
            [
                'start_date' => "$currentYear-01-01",
                'end_date' => "$currentYear-12-31",
                'status' => 'Open',
            ]
        );

        $this->command->info("Fiscal year {$fiscalYear->year_code} ready");
    }
}
