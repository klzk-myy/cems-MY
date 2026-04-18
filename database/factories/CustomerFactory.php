<?php

namespace Database\Factories;

use App\Enums\CddLevel;
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
            'risk_rating' => fake()->randomElement(['Low', 'Medium', 'High']),
            'risk_assessed_at' => now(),
            'last_transaction_at' => null,
        ];
    }
}
