<?php

namespace Database\Factories;

use App\Enums\EddStatus;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnhancedDiligenceRecordFactory extends Factory
{
    protected $model = EnhancedDiligenceRecord::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'edd_reference' => 'EDD-'.now()->format('Ym').'-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => EddStatus::PendingQuestionnaire,
            'risk_level' => fake()->randomElement(['Low', 'Medium', 'High', 'Critical']),
            'source_of_funds' => fake()->randomElement(['Salary', 'Business', 'Investment', 'Inheritance', 'Gift', 'Other']),
            'source_of_funds_description' => fake()->optional()->sentence(),
            'purpose_of_transaction' => fake()->randomElement(['Investment', 'Business Payment', 'Personal', 'Education', 'Travel', 'Other']),
            'business_justification' => fake()->optional()->sentence(),
            'employment_status' => fake()->randomElement(['Employed', 'Self-Employed', 'Business Owner', 'Retired', 'Unemployed']),
            'employer_name' => fake()->optional()->company(),
            'annual_income_range' => fake()->randomElement(['Below RM 30,000', 'RM 30,000 - RM 60,000', 'RM 60,000 - RM 100,000', 'RM 100,000 - RM 500,000', 'Above RM 500,000']),
            'estimated_net_worth' => fake()->randomElement(['Below RM 100,000', 'RM 100,000 - RM 500,000', 'RM 500,000 - RM 1,000,000', 'Above RM 1,000,000']),
            'source_of_wealth' => fake()->randomElement(['Salary', 'Business', 'Investment', 'Inheritance', 'Gift', 'Other']),
            'source_of_wealth_description' => fake()->optional()->sentence(),
            'additional_information' => fake()->optional()->paragraph(),
        ];
    }

    public function incomplete(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => EddStatus::Incomplete,
        ]);
    }

    public function pendingQuestionnaire(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => EddStatus::PendingQuestionnaire,
        ]);
    }

    public function pendingReview(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => EddStatus::PendingReview,
        ]);
    }

    public function approved(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => EddStatus::Approved,
            'reviewed_by' => 1,
            'reviewed_at' => now(),
            'review_notes' => 'Approved after review',
        ]);
    }

    public function rejected(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => EddStatus::Rejected,
            'reviewed_by' => 1,
            'reviewed_at' => now(),
            'review_notes' => 'Rejected due to insufficient documentation',
        ]);
    }
}
