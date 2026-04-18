<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\ScreeningResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreeningResult>
 */
class ScreeningResultFactory extends Factory
{
    protected $model = ScreeningResult::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'transaction_id' => null,
            'screened_name' => $this->faker->name(),
            'sanction_entry_id' => SanctionEntry::factory(),
            'match_type' => $this->faker->randomElement(['exact', 'levenshtein', 'soundex', 'metaphone', 'token']),
            'match_score' => $this->faker->randomFloat(2, 0.5, 1.0),
            'action_taken' => $this->faker->randomElement(['clear', 'flag', 'block']),
            'result' => $this->faker->randomElement(['clear', 'flag', 'block']),
            'matched_fields' => ['normalized_name' => $this->faker->name()],
        ];
    }

    public function clear(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_taken' => 'clear',
            'result' => 'clear',
            'match_score' => 0.0,
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_taken' => 'flag',
            'result' => 'flag',
            'match_score' => 0.75,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_taken' => 'block',
            'result' => 'block',
            'match_score' => 0.95,
        ]);
    }
}
