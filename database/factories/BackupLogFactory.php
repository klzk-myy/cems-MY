<?php

namespace Database\Factories;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupLog>
 */
class BackupLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'backup_name' => 'backup-'.now()->format('Y-m-d-H-i-s'),
            'backup_type' => $this->faker->randomElement(['full', 'incremental', 'differential']),
            'disk' => $this->faker->randomElement(['local', 's3', 'dropbox']),
            'file_path' => $this->faker->filePath(),
            'file_size' => $this->faker->numberBetween(1024, 1073741824), // 1KB to 1GB
            'checksum' => $this->faker->md5,
            'encryption_status' => $this->faker->boolean(90),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed', 'verified']),
            'started_at' => $this->faker->dateTimeThisMonth(),
            'completed_at' => $this->faker->optional()->dateTime(),
            'error_message' => $this->faker->optional()->sentence(),
            'metadata' => $this->faker->optional() ? [
                'compression' => $this->faker->randomElement(['gzip', 'bz2', 'none']),
                'checksum_verified' => true,
            ] : null,
            'verified_at' => $this->faker->optional()->dateTimeThisMonth(),
            'verification_status' => $this->faker->boolean(70),
            'verification_error' => $this->faker->optional()->sentence(),
        ];
    }
}
