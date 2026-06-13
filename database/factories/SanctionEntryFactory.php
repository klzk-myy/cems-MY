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
            'list_source' => $this->faker->randomElement(['ofac', 'un', 'eu', 'bnm', 'other']),
            'entity_name' => $name,
            'entity_type' => $this->faker->randomElement(['Individual', 'Organization', 'Vessel', 'Aircraft']),
            'aliases' => json_encode([$this->faker->name(), $this->faker->name()]),
            'nationality' => $this->faker->countryCode(),
            'date_of_birth' => $this->faker->date(),
            'reference_number' => strtoupper($this->faker->optional()->bothify('OFAC-#####')),
            'listing_date' => $this->faker->optional()->date(),
            'address' => $this->faker->optional()->streetAddress(),
            'city' => $this->faker->optional()->city(),
            'country' => $this->faker->optional()->country(),
            'postal_code' => $this->faker->optional()->postcode(),
            'details' => $this->faker->optional()->sentence(),
            'normalized_name' => mb_strtolower(trim($name)),
            'soundex_code' => soundex($name),
            'metaphone_code' => metaphone($name),
            'status' => 'active',
        ];
    }
}
