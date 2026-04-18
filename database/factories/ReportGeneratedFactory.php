<?php

namespace Database\Factories;

use App\Models\ReportGenerated;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportGeneratedFactory extends Factory
{
    protected $model = ReportGenerated::class;

    public function definition(): array
    {
        $reportTypes = ['LCTR', 'MSB2', 'trial_balance', 'pl', 'balance_sheet'];
        $reportType = fake()->randomElement($reportTypes);

        $periodStart = fake()->dateTimeBetween('-1 year', 'now');
        $periodEnd = fake()->dateTimeBetween($periodStart, '+1 month');

        return [
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'file_format' => 'CSV',
            'file_path' => fake()->optional()->filePath(),
            'status' => 'Generated',
        ];
    }
}
