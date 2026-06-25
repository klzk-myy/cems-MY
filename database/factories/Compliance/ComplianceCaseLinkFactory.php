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
            'linked_id' => function (array $attributes) {
                $type = $attributes['linked_type'] ?? 'App\Models\Customer';
                switch ($type) {
                    case 'App\Models\Customer':
                        return Customer::factory()->create()->id;
                    case 'App\Models\Transaction':
                        return Transaction::factory()->create()->id;
                    case 'App\Models\User':
                        return User::factory()->create()->id;
                    default:
                        return 1;
                }
            },
            'created_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
