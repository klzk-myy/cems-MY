<?php

namespace Database\Factories\Compliance;

use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceCase>
 */
class ComplianceCaseFactory extends Factory
{
    protected $model = ComplianceCase::class;

    public function definition(): array
    {
        return [
            'case_type' => fake()->randomElement(ComplianceCaseType::cases()),
            'status' => ComplianceCaseStatus::Open,
            'severity' => fake()->randomElement(FindingSeverity::cases()),
            'priority' => fake()->randomElement(ComplianceCasePriority::cases()),
            'customer_id' => Customer::factory(),
            'assigned_to' => User::factory(),
            'case_summary' => fake()->optional()->sentence(),
            'sla_deadline' => now()->addDays(7),
            'created_via' => fake()->randomElement(['Manual', 'Automated']),
        ];
    }

    /**
     * Indicate that the case is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceCaseStatus::Open,
        ]);
    }

    /**
     * Indicate that the case is under review.
     */
    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceCaseStatus::UnderReview,
        ]);
    }

    /**
     * Indicate that the case is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceCaseStatus::Closed,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Indicate that the case is escalated.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplianceCaseStatus::Escalated,
            'escalated_at' => now(),
        ]);
    }

    /**
     * Indicate that the case has critical severity.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Critical,
            'priority' => ComplianceCasePriority::Critical,
        ]);
    }
}
