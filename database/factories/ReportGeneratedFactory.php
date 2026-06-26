<?php

namespace Database\Factories;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportGeneratedFactory extends Factory
{
    protected $model = ReportGenerated::class;

    public function definition(): array
    {
        $reportType = $this->faker->randomElement(ReportType::cases());

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
            'status' => ReportGeneratedStatus::Generated->value,
        ];
    }
}
