<?php

namespace Database\Factories;

use App\Models\HighRiskCountry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HighRiskCountry>
 */
class HighRiskCountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $countryCode = strtoupper($this->faker->unique()->countryCode);

        return [
            'country_code' => $countryCode,
            'country_name' => $this->faker->country,
            'risk_level' => $this->faker->randomElement(['High', 'Grey']),
            'source' => $this->faker->randomElement(['UN', 'OFAC', 'EU', 'local']),
            'list_date' => $this->faker->date(),
        ];
    }
}
