<?php

namespace Database\Factories;

use App\Models\CounterSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounterSession>
 */
class CounterSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'status' => 'open',
        ];
    }
}
