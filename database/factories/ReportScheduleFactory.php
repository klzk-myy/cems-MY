<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    public function definition(): array
    {
        $reportTypes = ReportType::cases();
        $reportType = $this->faker->randomElement($reportTypes);

        $cronExpressions = [
            '0 0 * * *' => 'daily',
            '0 0 * * 1' => 'weekly',
            '0 0 1 * *' => 'monthly',
            '0 0 1 1,4,7,10 *' => 'quarterly',
        ];
        $cron = $this->faker->randomElement(array_keys($cronExpressions));

        return [
            'report_type' => $reportType,
            'cron_expression' => $cron,
            'parameters' => $this->faker->optional()->randomElement([
                ['format' => 'csv', 'email_recipients' => ['compliance@example.com']],
                ['format' => 'pdf', 'include_charts' => true],
                ['branch_filter' => 'all'],
                null,
            ]),
            'is_active' => $this->faker->boolean(70),
            'last_run_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'next_run_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notification_recipients' => $this->faker->optional()->email,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function dailyMsb2(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => ReportType::Msb2,
            'cron_expression' => '0 0 * * *',
            'parameters' => ['format' => 'csv', 'email_recipients' => ['compliance@example.com']],
            'is_active' => true,
        ]);
    }

    public function monthlyLmca(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => ReportType::Lmca,
            'cron_expression' => '0 0 1 * *',
            'parameters' => ['format' => 'pdf', 'include_charts' => true],
            'is_active' => true,
        ]);
    }

    public function quarterlyQlvr(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => ReportType::Qlvr,
            'cron_expression' => '0 0 1 1,4,7,10 *',
            'parameters' => ['format' => 'excel', 'email_recipients' => ['management@example.com']],
            'is_active' => true,
        ]);
    }
}
