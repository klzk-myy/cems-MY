<?php

namespace Database\Factories;

use App\Enums\SystemHealthCheckStatus;
use App\Models\SystemHealthCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemHealthCheckFactory extends Factory
{
    protected $model = SystemHealthCheck::class;

    public function definition(): array
    {
        $checkNames = [
            'database',
            'cache',
            'queue',
            'disk_space',
            'memory',
            'tests',
            'api_response_time',
            'error_rate',
        ];

        $statuses = SystemHealthCheckStatus::cases();

        return [
            'check_name' => $this->faker->randomElement($checkNames),
            'status' => $this->faker->randomElement($statuses),
            'message' => $this->faker->optional()->sentence(),
            'checked_at' => now(),
        ];
    }

    public function ok(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SystemHealthCheckStatus::Ok,
            'message' => 'Check passed successfully',
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SystemHealthCheckStatus::Warning,
            'message' => 'System performance degraded',
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SystemHealthCheckStatus::Critical,
            'message' => 'System failure detected',
        ]);
    }

    public function forCheck(string $checkName): static
    {
        return $this->state(fn (array $attributes) => [
            'check_name' => $checkName,
        ]);
    }
}
