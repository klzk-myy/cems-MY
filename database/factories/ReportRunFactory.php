<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportRunFactory extends Factory
{
    protected $model = ReportRun::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement([
            ReportStatus::Scheduled,
            ReportStatus::Running,
            ReportStatus::Completed,
            ReportStatus::Failed,
        ]);

        $startedAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $completedAt = match ($status->value) {
            ReportStatus::Completed->value, ReportStatus::Failed->value => clone $startedAt,
            default => null,
        };
        if ($completedAt) {
            $completedAt->modify('+'.$this->faker->numberBetween(10, 300).' seconds');
        }

        return [
            'schedule_id' => ReportSchedule::factory(),
            'report_type' => $this->faker->randomElement(ReportType::cases()),
            'parameters' => $this->faker->optional()->randomElement([
                ['format' => 'csv', 'include_totals' => true],
                ['format' => 'pdf', 'page_size' => 'A4'],
                ['branch_id' => $this->faker->randomNumber(3)],
                null,
            ]),
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'file_path' => $status === ReportStatus::Completed ? 'reports/'.$this->faker->unique()->word.'.csv' : null,
            'generated_by' => User::factory(),
            'row_count' => $status === ReportStatus::Completed ? $this->faker->numberBetween(100, 10000) : null,
            'error_message' => $status === ReportStatus::Failed ? $this->faker->sentence() : null,
            'downloaded_count' => $status === ReportStatus::Completed ? $this->faker->numberBetween(0, 50) : 0,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Scheduled,
            'started_at' => null,
            'completed_at' => null,
            'file_path' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Running,
            'completed_at' => null,
            'file_path' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'file_path' => 'reports/'.$this->faker->unique()->word.'.csv',
            'row_count' => $this->faker->numberBetween(100, 10000),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Failed,
            'completed_at' => now(),
            'file_path' => null,
            'row_count' => null,
            'error_message' => 'Report generation failed: '.$this->faker->sentence(),
        ]);
    }
}
