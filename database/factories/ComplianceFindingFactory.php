<?php

namespace Database\Factories;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Models\Compliance\ComplianceFinding;
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
            'finding_type' => $this->faker->randomElement(FindingType::cases()),
            'severity' => $this->faker->randomElement(FindingSeverity::cases()),
            'subject_type' => null,
            'subject_id' => null,
            'details' => ['description' => $this->faker->sentence()],
            'status' => $this->faker->randomElement(FindingStatus::cases()),
            'generated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Critical,
        ]);
    }

    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::High,
        ]);
    }

    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Medium,
        ]);
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => FindingSeverity::Low,
        ]);
    }

    public function new(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::New,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::UnderReview,
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::Dismissed,
        ]);
    }

    public function caseCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FindingStatus::CaseCreated,
        ]);
    }
}
