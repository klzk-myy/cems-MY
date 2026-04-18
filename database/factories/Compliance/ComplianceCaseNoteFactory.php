<?php

namespace Database\Factories\Compliance;

use App\Enums\CaseNoteType;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceCaseNote>
 */
class ComplianceCaseNoteFactory extends Factory
{
    protected $model = ComplianceCaseNote::class;

    public function definition(): array
    {
        return [
            'case_id' => ComplianceCase::factory(),
            'author_id' => User::factory(),
            'note_type' => fake()->randomElement(CaseNoteType::cases()),
            'content' => fake()->paragraph(),
            'is_internal' => fake()->boolean(70), // 70% chance of being internal
        ];
    }

    /**
     * Indicate that the note is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    /**
     * Indicate that the note is external.
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }
}
