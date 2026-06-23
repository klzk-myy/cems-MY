<?php

namespace Database\Factories;

use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionImport>
 */
class TransactionImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $filenameCounter = 0;

        return [
            'filename' => 'import_'.now()->format('YmdHis').'_'.(++$filenameCounter).'.csv',
            'original_filename' => 'transactions_'.++$filenameCounter.'.csv',
            'file_hash' => md5($this->faker->uuid),
            'file_size' => $this->faker->numberBetween(1024, 1048576), // 1KB to 1MB
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'total_rows' => $this->faker->numberBetween(1, 10000),
            'processed_rows' => $this->faker->numberBetween(0, 10000),
            'success_count' => $this->faker->numberBetween(0, 10000),
            'error_count' => $this->faker->numberBetween(0, 100),
            'error_details' => $this->faker->boolean(30) ? [
                'row' => $this->faker->numberBetween(1, 1000),
                'error' => $this->faker->sentence,
            ] : null,
            'imported_by' => User::factory(),
            'imported_at' => $this->faker->dateTimeThisMonth(),
            'completed_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
