<?php

namespace Database\Factories;

use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlaggedTransactionFactory extends Factory
{
    protected $model = FlaggedTransaction::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'flag_type' => fake()->randomElement(['Velocity', 'Structuring', 'EDD_Required', 'Sanction_Match', 'Manual_Review', 'Counterfeit_Currency']),
            'flag_reason' => fake()->sentence(),
            'status' => fake()->randomElement(['Open', 'Under_Review', 'Resolved']),
            'assigned_to' => null,
            'reviewed_by' => null,
            'notes' => null,
            'resolved_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
            'assigned_to' => null,
            'reviewed_by' => null,
            'resolved_at' => null,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Under_Review',
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Resolved',
            'resolved_at' => now(),
        ]);
    }

    public function counterfeit(): static
    {
        return $this->state(fn (array $attributes) => [
            'flag_type' => 'Counterfeit_Currency',
        ]);
    }
}
