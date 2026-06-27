<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Teller->value,
            'branch_id' => Branch::factory(),
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    public function make($attributes = [], ?Model $parent = null): User|Collection
    {
        $raw = $this->raw();
        $result = parent::make($attributes, $parent);
        $users = $result instanceof Collection
            ? $result
            : new Collection([$result]);

        $users->each(function (User $user) use ($raw) {
            if (array_key_exists('password_hash', $raw)) {
                $user->password_hash = $raw['password_hash'];
            } elseif (array_key_exists('password', $raw)) {
                $user->password_hash = is_string($raw['password'])
                    ? Hash::make($raw['password'])
                    : $raw['password'];
            } else {
                $user->password_hash = static::$password ??= Hash::make('password');
            }
        });

        return $result;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Manager,
        ]);
    }

    public function complianceOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ComplianceOfficer,
        ]);
    }

    public function teller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Teller,
        ]);
    }
}
