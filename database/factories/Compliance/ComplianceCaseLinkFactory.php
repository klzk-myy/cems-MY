<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceCaseLink>
 */
class ComplianceCaseLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => ComplianceCase::factory(),
            'linked_type' => $this->faker->randomElement(['App\Models\Customer', 'App\Models\Transaction', 'App\Models\User']),
            'linked_id' => function (array $attributes): int {
                /** @var Customer|Transaction|User $linked */
                $linked = match ($attributes['linked_type'] ?? 'App\\Models\\Customer') {
                    'App\\Models\\Transaction' => Transaction::factory()->create(),
                    'App\\Models\\User' => User::factory()->create(),
                    default => Customer::factory()->create(),
                };

                return $linked->id;
            },
            'created_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
