<?php

namespace Database\Factories;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'id_type' => fake()->randomElement(['MyKad', 'Passport', 'Others']),
            'id_number_encrypted' => fake()->uuid(),
            'nationality' => fake()->country(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'cdd_level' => fake()->randomElement(CddLevel::cases()),
            'pep_status' => false,
            'risk_score' => fake()->numberBetween(0, 100),
            'risk_rating' => fake()->randomElement([
                RiskRating::Low->value,
                RiskRating::Medium->value,
                RiskRating::High->value,
            ]),
            'risk_assessed_at' => now(),
            'last_transaction_at' => null,
            'is_frozen' => false,
            'freeze_reason' => null,
            'frozen_at' => null,
            'transactions_blocked' => false,
            'rejection_reason' => null,
            'is_active' => true,
        ];
    }

    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => true,
        ]);
    }

    public function pep(): static
    {
        return $this->state(fn (array $attributes) => [
            'pep_status' => true,
        ]);
    }

    public function sanctioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'sanction_hit' => true,
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_rating' => RiskRating::High,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
