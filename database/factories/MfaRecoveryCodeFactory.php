<?php

namespace Database\Factories;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MfaRecoveryCode>
 */
class MfaRecoveryCodeFactory extends Factory
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
            'code_hash' => bcrypt($this->faker->regexify('[A-Z0-9]{10}')),
            'used' => $this->faker->boolean(20),
            'used_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
