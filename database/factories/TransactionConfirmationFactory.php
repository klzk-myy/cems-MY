<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionConfirmationFactory extends Factory
{
    protected $model = TransactionConfirmation::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'rejected', 'expired']),
            'confirmation_token' => Str::random(32),
        ];
    }
}
