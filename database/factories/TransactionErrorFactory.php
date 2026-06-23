<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionError;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionErrorFactory extends Factory
{
    protected $model = TransactionError::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'error_type' => $this->faker->randomElement([
                'processing_error',
                'validation_error',
                'compliance_error',
                'accounting_error',
                'stock_error',
                'network_error',
                'deadlock_error',
                'timeout_error',
            ]),
            'error_message' => $this->faker->sentence(),
            'error_context' => [
                'file' => $this->faker->filePath(),
                'line' => $this->faker->numberBetween(1, 1000),
            ],
            'retry_count' => 0,
            'max_retries' => 3,
            'next_retry_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => now(),
            'resolved_by' => 1,
            'resolution_notes' => 'Manually resolved',
        ]);
    }

    public function maxRetriesReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => 3,
            'max_retries' => 3,
        ]);
    }

    public function readyForRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => 1,
            'max_retries' => 3,
            'next_retry_at' => now()->subSecond(),
        ]);
    }

    public function processingError(): static
    {
        return $this->state(fn (array $attributes) => [
            'error_type' => 'processing_error',
        ]);
    }

    public function validationError(): static
    {
        return $this->state(fn (array $attributes) => [
            'error_type' => 'validation_error',
        ]);
    }

    public function complianceError(): static
    {
        return $this->state(fn (array $attributes) => [
            'error_type' => 'compliance_error',
        ]);
    }
}
