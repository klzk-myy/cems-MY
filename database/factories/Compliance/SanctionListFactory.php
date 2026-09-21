<?php

namespace Database\Factories\Compliance;

use App\Enums\SanctionListType;
use App\Models\Compliance\SanctionList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SanctionList>
 */
class SanctionListFactory extends Factory
{
    protected $model = SanctionList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Sanctions List',
            'slug' => $this->faker->unique()->slug(2),
            'list_type' => $this->faker->randomElement(SanctionListType::cases()),
            'source_file' => $this->faker->word().'.csv',
            'uploaded_by' => User::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the list is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
