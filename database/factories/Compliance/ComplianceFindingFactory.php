<?php

namespace Database\Factories\Compliance;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceFinding>
 */
class ComplianceFindingFactory extends Factory
{
    protected $model = ComplianceFinding::class;

    public function definition(): array
    {
        return [
            'finding_type' => fake()->randomElement(FindingType::cases()),
            'severity' => fake()->randomElement(FindingSeverity::cases()),
            'subject_type' => Customer::class,
            'subject_id' => Customer::factory(),
            'details' => [
                'description' => fake()->sentence(),
                'detected_at' => now()->toIso8601String(),
            ],
            'status' => FindingStatus::New,
            'generated_at' => now(),
        ];
    }

    /**
     * Indicate that the finding is new.
     */
    public function asNew(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::New,
        ]);
    }

    /**
     * Indicate that the finding is dismissed.
     */
    public function asDismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Dismissed,
        ]);
    }

    /**
     * Indicate that the finding has critical severity.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Critical,
        ]);
    }

    /**
     * Indicate that the finding is a sanction match.
     */
    public function sanctionMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'finding_type' => FindingType::SanctionMatch,
            'severity' => FindingSeverity::Critical,
        ]);
    }

    /**
     * Indicate that the finding is a velocity exceedance.
     */
    public function velocityExceeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'finding_type' => FindingType::VelocityExceeded,
            'severity' => FindingSeverity::High,
        ]);
    }
}
