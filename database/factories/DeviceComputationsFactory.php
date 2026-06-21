<?php

namespace Database\Factories;

use App\Models\DeviceComputations;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceComputations>
 */
class DeviceComputationsFactory extends Factory
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
            'device_name' => $this->faker->word.'-device',
            'device_fingerprint' => sha1($this->faker->uuid),
            'ip_address' => $this->faker->ipv4,
            'expires_at' => $this->faker->optional()->dateTimeThisYear(),
            'last_used_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
