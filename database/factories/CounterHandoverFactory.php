<?php

namespace Database\Factories;

use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounterHandover>
 */
class CounterHandoverFactory extends Factory
{
    protected $model = CounterHandover::class;

    public function definition(): array
    {
        return [
            'counter_session_id' => fn () => CounterSession::factory()->create()->id,
            'from_user_id' => fn () => User::factory()->create()->id,
            'to_user_id' => fn () => User::factory()->create()->id,
            'supervisor_id' => fn () => User::factory()->create()->id,
            'handover_time' => now(),
            'physical_count_verified' => true,
            'variance_myr' => '0.00',
        ];
    }
}
