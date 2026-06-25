<?php

namespace Database\Factories;

use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyClosure>
 */
class EmergencyClosureFactory extends Factory
{
    protected $model = EmergencyClosure::class;

    public function definition(): array
    {
        return [
            'counter_id' => fn () => Counter::factory()->create()->id,
            'session_id' => fn () => CounterSession::factory()->create()->id,
            'teller_id' => fn () => User::factory()->create()->id,
            'reason' => fake()->sentence(),
            'closed_at' => now(),
        ];
    }
}
