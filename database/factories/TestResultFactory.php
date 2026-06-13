<?php

namespace Database\Factories;

use App\Enums\TestResultStatus;
use App\Models\TestResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestResultFactory extends Factory
{
    protected $model = TestResult::class;

    public function definition(): array
    {
        $totalTests = fake()->numberBetween(10, 100);
        $passed = fake()->numberBetween(0, $totalTests);
        $failed = fake()->numberBetween(0, $totalTests - $passed);

        return [
            'run_id' => fake()->uuid(),
            'test_suite' => fake()->randomElement(['full', 'Feature', 'Unit', 'Browser']),
            'total_tests' => $totalTests,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $totalTests - $passed - $failed,
            'assertions' => fake()->numberBetween(10, 200),
            'duration' => fake()->randomFloat(2, 1, 60),
            'status' => fake()->randomElement(TestResultStatus::cases()),
            'output' => null,
            'failures' => null,
            'errors' => null,
            'git_branch' => fake()->optional()->word(),
            'git_commit' => fake()->optional()->sha256(),
            'executed_by' => fake()->optional()->userName(),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }
}
