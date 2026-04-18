<?php

namespace Database\Factories;

use App\Models\SanctionEntry;
use App\Models\SanctionList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SanctionEntry>
 */
class SanctionEntryFactory extends Factory
{
    protected $model = SanctionEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'list_id' => SanctionList::factory(),
            'entity_name' => $name,
            'entity_type' => $this->faker->randomElement(['Individual', 'Entity']),
            'aliases' => json_encode([$this->faker->name(), $this->faker->name()]),
            'nationality' => $this->faker->countryCode(),
            'date_of_birth' => $this->faker->date(),
            'details' => json_encode(['source' => 'factory']),
            'normalized_name' => mb_strtolower(trim($name)),
            'soundex_code' => soundex($name),
            'metaphone_code' => metaphone($name),
            'status' => 'active',
        ];
    }
}
